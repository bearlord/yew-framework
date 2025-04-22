<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Server\Config\PortConfig;
use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Route\Filter\CorsFilter;
use Yew\Plugins\Route\Filter\XmlResponseFilter;
use Yew\Plugins\AnnotationsScan\AnnotationsScanPlugin;
use Yew\Plugins\AnnotationsScan\ScanClass;
use Yew\Plugins\AnnotationsScan\ScanReflectionMethod;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Route\Annotation\Controller;
use Yew\Plugins\Route\Annotation\RequestMapping;
use Yew\Plugins\Route\Aspect\RouteAspect;
use Yew\Plugins\Route\Filter\FilterManager;
use Yew\Plugins\Route\Filter\JsonResponseFilter;
use Yew\Plugins\Route\Filter\ServerFilter;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Pack\ClientDataProxy;
use Yew\Plugins\Pack\PackPlugin;
use Yew\Plugins\Validate\ValidatePlugin;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Nikic\FastRoute\RouteCollector;
use ReflectionClass;
use ReflectionMethod;
use function Yew\Nikic\FastRoute\simpleDispatcher;

/**
 * Class EasyRoutePlugin
 * @package Yew\Plugins\Route
 */
class RoutePlugin extends AbstractPlugin
{
    public static $instance;

    /**
     * @var RoutePortConfig[]
     */
    private $RoutePortConfigs = [];

    /**
     * @var RouteConfig
     */
    private $routeConfig;

    /**
     * @var RouteAspect
     */
    private $routeAspect;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var ScanClass
     */
    private $scanClass;

    /**
     * @var FilterManager
     */
    private $filterManager;

    /**
     * EasyRoutePlugin constructor.
     * @param RouteConfig|null $routeConfig
     * @throws \Exception
     */
    public function __construct(?RouteConfig $routeConfig = null)
    {
        parent::__construct();
        if ($routeConfig == null) {
            $routeConfig = new RouteConfig();
        }
        $this->routeConfig = $routeConfig;

        $this->atAfter(AnnotationsScanPlugin::class);
        $this->atAfter(ValidatePlugin::class);
        $this->atAfter(PackPlugin::class);
        $this->filterManager = DIGet(FilterManager::class);
        self::$instance = $this;
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Route";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function init(Context $context)
    {
        parent::init($context);
        $configs = Server::$instance->getConfigContext()->get(PortConfig::KEY);
        foreach ($configs as $key => $value) {
            $RoutePortConfig = new RoutePortConfig();
            $RoutePortConfig->setName($key);
            $RoutePortConfig->buildFromConfig($value);
            $RoutePortConfig->merge();
            $this->RoutePortConfigs[$RoutePortConfig->getPort()] = $RoutePortConfig;
        }
        $this->routeConfig->merge();
        $aopConfig = DIget(AopConfig::class);
        $this->routeAspect = new RouteAspect($this->RoutePortConfigs, $this->routeConfig);
        $aopConfig->addAspect($this->routeAspect);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AnnotationsScanPlugin());
        $pluginInterfaceManager->addPlugin(new ValidatePlugin());
        $pluginInterfaceManager->addPlugin(new PackPlugin());
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function beforeServerStart(Context $context)
    {
        $this->routeConfig->merge();
        $this->setToDIContainer(ClientData::class, new ClientDataProxy());
        $this->filterManager->addFilter(new ServerFilter());
        $this->filterManager->addFilter(new CorsFilter());
        $this->filterManager->addFilter(new JsonResponseFilter());
        $this->filterManager->addFilter(new XmlResponseFilter());

    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessType() != Process::PROCESS_TYPE_WORKER) {
            $this->ready();
            return;
        }
        $this->scanClass = DIget(ScanClass::class);
        $reflectionMethods = $this->scanClass->findMethodsByAnnotation(RequestMapping::class);
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) use ($reflectionMethods) {
            //Add route in configuration
            foreach ($this->routeConfig->getRouteRoles() as $routeRole) {
                $reflectionClass = new ReflectionClass($routeRole->getController());
                $reflectionMethod = new ScanReflectionMethod($reflectionClass, new ReflectionMethod($routeRole->getController(), $routeRole->getMethod()));
                $this->addRoute($routeRole, $r, $reflectionClass, $reflectionMethod);
            }

            //Add route in the comment
            foreach ($reflectionMethods as $reflectionMethod) {
                $reflectionClass = $reflectionMethod->getParentReflectClass();
                if ($this->scanClass->getCachedReader()->getClassAnnotation($reflectionClass, Controller::class) == null) {
                    continue;
                }
                $route = "/";
                $requestMapping = $this->scanClass->getClassAndInterfaceAnnotation($reflectionClass, RequestMapping::class);
                $controller = $this->scanClass->getCachedReader()->getClassAnnotation($reflectionClass, Controller::class);
                if ($controller instanceof Controller) {
                    $controller->value = trim($controller->value, "/");
                    $route .= $controller->value;
                }
                if ($requestMapping instanceof RequestMapping) {
                    $route = "/";
                    $requestMapping->value = trim($requestMapping->value, "/");
                    $route .= $requestMapping->value;
                }
                $requestMapping = $this->scanClass->getMethodAndInterfaceAnnotation($reflectionMethod->getReflectionMethod(), RequestMapping::class);
                if ($requestMapping instanceof RequestMapping) {
                    if (empty($requestMapping->value)) {
                        $requestMapping->value = $reflectionMethod->getName();
                    }
                    $requestMapping->value = trim($requestMapping->value, "/");
                    if ($route == "/") {
                        $route .= $requestMapping->value;
                    } else {
                        $route .= "/" . $requestMapping->value;
                    }

                    if (empty($requestMapping->method)) {
                        $requestMapping->method[] = $controller->defaultMethod;
                    }

                    foreach ($requestMapping->method as $method) {
                        $routeRole = new RouteRoleConfig();
                        $routeRole->setRoute($route);
                        $routeRole->setType($method);
                        $routeRole->setController($reflectionClass->getName());
                        $routeRole->setMethod($reflectionMethod->getName());
                        $routeRole->setPortNames($controller->portNames);
                        $routeRole->setPortTypes($controller->portTypes);
                        $routeRole->buildName();
                        $this->routeConfig->addRouteRole($routeRole);
                        $this->addRoute($routeRole, $r, $reflectionClass, $reflectionMethod);
                    }
                }
            }
        });
        $this->routeConfig->merge();
        $this->ready();
    }

    /**
     * @param RouteRoleConfig $routeRole
     * @param RouteCollector $r
     * @param $reflectionClass
     * @param $reflectionMethod
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    protected function addRoute(RouteRoleConfig $routeRole, RouteCollector $r, $reflectionClass, $reflectionMethod)
    {
        $couldPortNames = [];
        if (!empty($routeRole->getPortTypes())) {
            foreach ($routeRole->getPortTypes() as $portType) {
                foreach ($this->RoutePortConfigs as $RoutePortConfig) {
                    if ($RoutePortConfig->getBaseType() == $portType) {
                        $couldPortNames[] = $RoutePortConfig->getName();
                    }
                }
            }
        } else {
            foreach ($this->RoutePortConfigs as $RoutePortConfig) {
                $couldPortNames[] = $RoutePortConfig->getName();
            }
        }
        //Array intersect
        if (!empty($routeRole->getPortNames())) {
            $couldPortNames = array_intersect($couldPortNames, $routeRole->getPortNames());
        }

        foreach ($couldPortNames as $portName) {
            $type = strtoupper($routeRole->getType());
            $port = Server::$instance->getPortManager()->getPortConfigs()[$portName]->getPort();
            if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                $message = sprintf("%s:%-7s %s -> %s::%s", $port, $type, $routeRole->getRoute(), $reflectionClass->name, $reflectionMethod->name);

                Server::$instance->getLog()->info($message);
            }
            $r->addRoute("$port:{$type}", $routeRole->getRoute(), [$reflectionClass, $reflectionMethod]);
        }
    }

    /**
     * @return RouteAspect
     */
    public function getRouteAspect(): RouteAspect
    {
        return $this->routeAspect;
    }

    /**
     * @return Dispatcher
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    /**
     * @return ScanClass
     */
    public function getScanClass(): ScanClass
    {
        return $this->scanClass;
    }
}