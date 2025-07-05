<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actuator;

use Yew\Core\Context\Context;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;
use ESD\Plugins\Actuator\Aspect\ActuatorAspect;
use ESD\Plugins\Actuator\Aspect\CountAspect;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Aop\AopPlugin;
use Yew\Plugins\Route\RoutePlugin;
use Yew\Yew;
use Yew\Nikic\FastRoute\RouteCollector;
use function Yew\Nikic\FastRoute\simpleDispatcher;


class ActuatorPlugin extends AbstractPlugin
{
    use GetLogger;

    /**
     * @var Table Table
     */
    protected $table;


    public function __construct()
    {
        parent::__construct();

        //Need aop support, so load after aop
        $this->atAfter(AopPlugin::class);

        //Due to Aspect sorting issues need to be loaded before EasyRoutePlugin
        $this->atBefore(EasyRoutePlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $aopPlugin = $pluginInterfaceManager->getPlug(AopPlugin::class);
        if ($aopPlugin == null) {
            $aopPlugin = new AopPlugin();
            $pluginInterfaceManager->addPlugin($aopPlugin);
        }
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return "Actuator";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Yew\Core\Exception\Exception
     */
    public function init(Context $context)
    {
        parent::init($context);
        $serverConfig = Server::$instance->getServerConfig();
        $aopConfig = DIget(AopConfig::class);
        $actuatorController = new ActuatorController();

        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            $r->addRoute("GET", "/actuator", "index");
            $r->addRoute("GET", "/actuator/health", "health");
            $r->addRoute("GET", "/actuator/info", "info");
        });

        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/esd-framework/src/ESD/");
        $aopConfig->addAspect(new ActuatorAspect($actuatorController, $dispatcher));
        $aopConfig->addAspect(new CountAspect());
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        /**
         *
         * 1byte(int8)：-127 ~ 127
         * 2byte(int16)：-32767 ~ 32767
         * 4byte(int32)：-2147483647 ~ 2147483647
         * 8byte(int64)：不会溢出
         */
        $table = new Table(1024);
        $table->column('num_60', Table::TYPE_INT, 4);
        $table->column('num_3600', Table::TYPE_INT, 4);
        $table->column('num_86400', Table::TYPE_INT, 4);
        if (!$table->create()) {
            throw new \Exception('memory not allowed');
        }

        $this->table = $table;

        $this->setToDIContainer('RouteCountTable', $table);
        return;
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessType() != Process::PROCESS_TYPE_WORKER) {
            $this->ready();
            return;
        }

        addTimerTick(60 * 1000, function () {
            $this->updateCount('num_60');
        });

        addTimerTick(3600 * 1000, function () {
            $this->updateCount('num_3600');
        });

        addTimerTick(86400 * 1000, function () {
            $this->updateCount('num_86400');
        });

        $this->ready();
    }

    /**
     * @param string $column
     * @return void
     */
    public function updateCount(string $column)
    {
        foreach ($this->table as $key => $num) {
            $this->table->set($key, [$column => 0]);
            $this->debug(sprintf("%s %s:%s -> 0", 'Update count', $key, $column));
        }
    }
}