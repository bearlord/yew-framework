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
use Yew\Plugins\Actuator\Aspect\ActuatorAspect;
use Yew\Plugins\Actuator\Aspect\CountAspect;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Aop\AopPlugin;
use Yew\Plugins\Route\RoutePlugin;
use Yew\Yew;
use Yew\Nikic\FastRoute\RouteCollector;
use function Yew\Nikic\FastRoute\simpleDispatcher;

/**
 * Adds /actuator endpoints and per-route request counters.
 */
class ActuatorPlugin extends AbstractPlugin
{
    use GetLogger;

    protected ?Table $table = null;

    public function __construct()
    {
        parent::__construct();
        $this->atAfter(AopPlugin::class);
        $this->atBefore(EasyRoutePlugin::class);
    }

    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $aopPlugin = $pluginInterfaceManager->getPlug(AopPlugin::class);
        if ($aopPlugin == null) {
            $pluginInterfaceManager->addPlugin(new AopPlugin());
        }
    }

    public function getName(): string
    {
        return "Actuator";
    }

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

        $aopConfig->addIncludePath($serverConfig->getVendorDir() . "/yew-framework/src/");
        $aopConfig->addAspect(new ActuatorAspect($actuatorController, $dispatcher));
        $aopConfig->addAspect(new CountAspect());
    }

    public function beforeServerStart(Context $context)
    {
        $table = new Table(1024);
        $table->column("num_60", Table::TYPE_INT, 4);
        $table->column("num_3600", Table::TYPE_INT, 4);
        $table->column("num_86400", Table::TYPE_INT, 4);
        if (!$table->create()) {
            throw new \Exception("memory not allowed");
        }

        $this->table = $table;
        $this->setToDIContainer("RouteCountTable", $table);
    }

    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessType() != Process::PROCESS_TYPE_WORKER) {
            $this->ready();
            return;
        }

        addTimerTick(60 * 1000, fn() => $this->updateCount("num_60"));
        addTimerTick(3600 * 1000, fn() => $this->updateCount("num_3600"));
        addTimerTick(86400 * 1000, fn() => $this->updateCount("num_86400"));

        $this->ready();
    }

    /**
     * Reset a counter column to 0 for every route (sliding window reset).
     */
    public function updateCount(string $column): void
    {
        foreach ($this->table as $key => $num) {
            $this->table->set($key, [$column => 0]);
            $this->debug(sprintf("Update count %s:%s -> 0", $key, $column));
        }
    }
}
