<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew;

namespace Yew\Framework;

use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use ReflectionException;
use Yew\Core\Exception\ConfigException;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Plugins\Yew\YewPlugin;
use Yew\Core\Server\Config\ServerConfig;
use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;
use Yew\Nikic\FastRoute\RouteCollector;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Aop\AopPlugin;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Console\ConsolePlugin;
use Yew\Plugins\Redis\RedisPlugin;
use Yew\Plugins\Route\RouteConfig;
use Yew\Plugins\Route\RoutePlugin;

class Application extends Server
{
    use GetLogger;

    /**
     * @var OrderAspect[]
     */
    protected array $aspects = [];

    public function __construct(?ServerConfig $serverConfig = null)
    {
        parent::__construct($serverConfig, AppPort::class, AppProcess::class);

        $this->prepareNormalPlugins();
    }

    /**
     * @param string $mainClass
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws ConfigException
     */
    public function run(string $mainClass)
    {

        $this->configure();
        $this->getContainer()->get($mainClass);
        $this->start();
    }

    /**
     * @return void
     * @throws ConfigException
     * @throws ReflectionException
     */
    public function prepareNormalPlugins()
    {
        $this->addNormalPlugins();
    }

    /**
     * @return void
     * @throws ConfigException
     * @throws ReflectionException
     */
    protected function addNormalPlugins()
    {
//        $this->addPlugin(new ConsolePlugin());
//        $this->addPlugin(new YiiPlugin());
//        $routeConfig = new RouteConfig();
//        $routeConfig->setErrorControllerName(GoController::class);
//
//        $this->addPlugin(new EasyRoutePlugin($routeConfig));
//        //$this->addPlugin(new ScheduledPlugin());
//        $this->addPlugin(new RedisPlugin());
//        $this->addPlugin(new AutoreloadPlugin());
//        $this->addPlugin(new AopPlugin());
//        $this->addPlugin(new SaberPlugin());
//        //$this->addPlugin(new ActuatorPlugin());
//        $this->addPlugin(new WhoopsPlugin());
//        $this->addPlugin(new SessionPlugin());
//        $this->addPlugin(new CachePlugin());
//        $this->addPlugin(new SecurityPlugin());
//        $this->addPlugin(new PHPUnitPlugin());
//        $this->addPlugin(new ProcessRPCPlugin());
//        $this->addPlugin(new UidPlugin());
//        $this->addPlugin(new TopicPlugin());
//        $this->addPlugin(new BladePlugin());
//
        $this->addPlugin(new YewPlugin());
        $this->addPlugin(new ConsolePlugin());

        $this->addPlugin(new AopPlugin());

        $routeConfig = new RouteConfig();
        $routeConfig->setErrorControllerName(RouteCollector::class);

        $this->addPlugin(new RoutePlugin($routeConfig));

        $this->addPlugin(new RedisPlugin());

        //Add aop of Go namespace by default
        $aopConfig = new AopConfig(__DIR__);
        $aopConfig->merge();
    }

    /**
     * @param AbstractPlugin $plugin
     * @return void
     */
    public function addPlugin(AbstractPlugin $plugin)
    {
        $this->getPlugManager()->addPlugin($plugin);
    }


    public function configureReady()
    {
        $this->debug('Configure ready');
    }

    public function onStart()
    {
        $this->debug('Application start');
    }

    public function onShutdown()
    {
        $this->debug('Application shutdown');
    }

    public function onWorkerError(Process $process, int $exitCode, int $signal)
    {
        $this->debug('Manager process start');
    }

    public function onManagerStart()
    {
        $this->debug('Manager process start');
    }

    public function onManagerStop()
    {
        $this->debug('Manager process stop');
    }

    public function pluginInitialized()
    {
        $this->addAspects();
    }

    /**
     * Add AOP aspect
     * @return void
     * @throws Exception
     */
    protected function addAspects()
    {
        foreach ($this->aspects as $aspect){
            /** @var AopConfig $aopConfig */
            $aopConfig = DIGet(AopConfig::class);
            $aopConfig->addAspect($aspect);
        }
    }

    /**
     * Add AOP aspect
     * @param OrderAspect $orderAspect
     */
    public function addAspect(OrderAspect $orderAspect)
    {
        $this->aspects[] = $orderAspect;
    }
}