<?php
/**
 * ESD framework
 * @author tmtbe <896369042@qq.com>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Aspect;

use Yew\Core\Exception\Exception;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Route\Controller\IController;
use Yew\Plugins\Route\RoutePortConfig;
use Yew\Plugins\Route\RoutePlugin;
use Yew\Plugins\Route\Filter\AbstractFilter;
use Yew\Plugins\Route\Filter\FilterManager;
use Yew\Plugins\Route\RouteConfig;
use Yew\Plugins\Route\RouteException;
use Yew\Plugins\Route\RouteTool\IRoute;
use Yew\Plugins\Pack\Aspect\PackAspect;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Around;
use Yew\Goaop\Lang\Annotation\After;
use Yew\Goaop\Lang\Annotation\Before;
use Yew\Utils\ArrayToXml;

/**
 * Class RouteAspect
 * @package Yew\Plugins\Route\Aspect
 */
class RouteAspect extends OrderAspect
{
    use GetLogger;
    use GetBoostSend;

    /**
     * @var RoutePortConfig[]
     */
    protected array $RoutePortConfigs;
    /**
     * @var IRoute[]
     */
    protected array $routeTools = [];

    /**
     * @var IController[]
     */
    protected array $controllers = [];

    /**
     * @var RouteConfig
     */
    protected RouteConfig $routeConfig;

    /**
     * @var FilterManager
     */
    protected $filterManager;

    /**
     * RouteAspect constructor.
     * @param $RoutePortConfigs
     * @param RouteConfig $routeConfig
     * @throws \Exception
     */
    public function __construct($RoutePortConfigs, RouteConfig $routeConfig)
    {
        $this->RoutePortConfigs = $RoutePortConfigs;
        foreach ($this->RoutePortConfigs as $RoutePortConfig) {
            if (!isset($this->routeTools[$RoutePortConfig->getRouteTool()])) {
                $className = $RoutePortConfig->getRouteTool();
                $this->routeTools[$RoutePortConfig->getRouteTool()] = DIget($className);
            }
        }

        $this->routeConfig = $routeConfig;

        $this->filterManager = DIGet(FilterManager::class);

        $this->atAfter(PackAspect::class);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "RouteAspect";
    }

    /**
     * Around onHttpRequest
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onHttpRequest(*))")
     */
    protected function aroundHttpRequest(MethodInvocation $invocation)
    {
        $abstractServerPort = $invocation->getThis();
        $RoutePortConfig = $this->RoutePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("RoutePortConfig", $RoutePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$RoutePortConfig->getRouteTool()];

        try {
            if (!$routeTool->handleClientData($clientData, $RoutePortConfig)) {
                return;
            }

            $controllerInstance = $this->getController($routeTool->getControllerName());
            $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());

            $handleResult = $controllerInstance->handle($routeTool->getControllerName(), $routeTool->getMethodName(), $routeTool->getParams());
            $clientData->setResponseRaw($handleResult);

            if ($this->filterManager->filter(AbstractFilter::FILTER_ROUTE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
                return;
            }

            $clientData->getResponse()->append($clientData->getResponseRaw());

            $this->filterManager->filter(AbstractFilter::FILTER_PRO, $clientData);
        } catch (\Throwable $e) {
            //The errors here will be handed over to the IndexController
            $controllerInstance = $this->getController($this->routeConfig->getErrorControllerName());
            $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());

            $result = $controllerInstance->onExceptionHandle($e);
            if (!empty($result)) {
                $clientData->getResponse()->append($result);
            }
            throw $e;
        }
    }

    /**
     * Get controller
     *
     * @param $controllerName
     * @return IController
     * @throws RouteException
     */
    private function getController($controllerName): ?IController
    {
        if (empty($controllerName)) {
            throw new RouteException("Controller name not found");
        }
        if (!isset($this->controllers[$controllerName])) {
            if (class_exists($controllerName)) {
                $controller = DIget($controllerName);
                if ($controller instanceof IController) {
                    $this->controllers[$controllerName] = $controller;
                    return $controller;
                } else {
                    throw new RouteException(sprintf("Class %s should extend IController", $controllerName));
                }
            } else {
                throw new RouteException(sprintf("%s Not found", $controllerName));
            }
        } else {
            return $this->controllers[$controllerName];
        }
    }

    /**
     * After onTcpConnect
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpConnect(*))")
     */
    protected function afterTcpConnect(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        $clientInfo = Server::$instance->getClientInfo($fd);
        //Server port
        $serverPort = $clientInfo->getServerPort();
        //Request method
        $requestMethod = 'TCP';
        //Defined path
        $onConnectPath = '/onConnect';
        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onConnectPath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * Around onTcpReceive
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpReceive(*))")
     */
    protected function aroundTcpReceive(MethodInvocation $invocation)
    {
        $abstractServerPort = $invocation->getThis();
        $RoutePortConfig = $this->RoutePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("RoutePortConfig", $RoutePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$RoutePortConfig->getRouteTool()];

        try {
            if (!$routeTool->handleClientData($clientData, $RoutePortConfig)) {
                return;
            }
            $controllerInstance = $this->getController($routeTool->getControllerName());
            $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());

            $clientData->setResponseRaw($controllerInstance->handle($routeTool->getControllerName(), $routeTool->getMethodName(), $routeTool->getParams()));
            if ($this->filterManager->filter(AbstractFilter::FILTER_ROUTE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
                return;
            }
            if ($RoutePortConfig->getAutoSendReturnValue()) {
                $this->autoBoostSend($clientData->getFd(), $clientData->getResponseRaw());
            }
        } catch (\Throwable $e) {
            try {
                //The errors here will be handed over to the IndexController
                $controllerInstance = $this->getController($this->routeConfig->getErrorControllerName());
                $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());
                $controllerInstance->onExceptionHandle($e);
            } catch (\Throwable $e) {
                $this->warn($e);
            }
            throw $e;
        }
    }

    /**
     * Before onTcpClose
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Before("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpClose(*))")
     */
    protected function beforeTcpClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        $clientInfo = Server::$instance->getClientInfo($fd);
        //Server port
        $serverPort = $clientInfo->getServerPort();
        //Request method
        $requestMethod = 'TCP';
        //Defined path
        $onClosePath = '/beforeClose';
        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * After onTcpClose
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpClose(*))")
     */
    protected function afterTcpClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        $clientInfo = Server::$instance->getClientInfo($fd);
        //Server port
        $serverPort = $clientInfo->getServerPort();
        //Request method
        $requestMethod = 'TCP';
        //Defined path
        $onClosePath = '/onClose';
        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * After onWsOpen
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsOpen(*))")
     */
    protected function afterWsOpen(MethodInvocation $invocation)
    {
        $request = $invocation->getArguments()[0];
        //fd
        $fd = $request->getFd();
        //Client Info
        $clientInfo = Server::$instance->getClientInfo($fd);
        //ReactorId
        $reactorId = $clientInfo->getReactorId();
        //Server port
        $serverPort = $clientInfo->getServerPort();
        //Request method
        $requestMethod = 'WS';
        //Defined path
        $onConnectPath = '/onWsOpen';
        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onConnectPath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId, $request]);
    }

    /**
     * Around onWsMessage
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsMessage(*))")
     */
    protected function aroundWsMessage(MethodInvocation $invocation)
    {
        $abstractServerPort = $invocation->getThis();
        $RoutePortConfig = $this->RoutePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("RoutePortConfig", $RoutePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$RoutePortConfig->getRouteTool()];
        try {
            if (!$routeTool->handleClientData($clientData, $RoutePortConfig)) {
                return;
            }
            $controllerInstance = $this->getController($routeTool->getControllerName());
            $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());

            $clientData->setResponseRaw($controllerInstance->handle($routeTool->getControllerName(), $routeTool->getMethodName(), $routeTool->getParams()));

            if ($this->filterManager->filter(AbstractFilter::FILTER_ROUTE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
                return;
            }
            if ($RoutePortConfig->getAutoSendReturnValue()) {
                $this->autoBoostSend($clientData->getFd(), $clientData->getResponseRaw());
            }
        } catch (\Throwable $e) {
            try {
                //The errors here will be handed over to the IndexController
                $controllerInstance = $this->getController($this->routeConfig->getErrorControllerName());
                $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());
                $controllerInstance->onExceptionHandle($e);
            } catch (\Throwable $e) {
                $this->warn($e);
            }
            throw $e;
        }
    }

    /**
     * Before onWsClose
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Before("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsClose(*))")
     */
    protected function beforeWSClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        $clientInfo = Server::$instance->getClientInfo($fd);
        //Server port
        $serverPort = $clientInfo->getServerPort();
        //Request method
        $requestMethod = 'WS';
        //Define path
        $onClosePath = '/beforeWsClose';
        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * After onWsClose
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsClose(*))")
     */
    protected function afterWSClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        $clientInfo = Server::$instance->getClientInfo($fd);
        //Server port
        $serverPort = $clientInfo->getServerPort();
        //Request method
        $requestMethod = 'WS';
        //Define path
        $onClosePath = '/onWsClose';
        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * Around onUdpPacket
     *
     * @param MethodInvocation $invocation Invocation
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onUdpPacket(*))")
     * @throws \Throwable
     */
    protected function aroundUdpPacket(MethodInvocation $invocation)
    {
        $abstractServerPort = $invocation->getThis();
        $RoutePortConfig = $this->RoutePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("RoutePortConfig", $RoutePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$RoutePortConfig->getRouteTool()];
        try {
            if (!$routeTool->handleClientData($clientData, $RoutePortConfig)) {
                return;
            }
            $controllerInstance = $this->getController($routeTool->getControllerName());
            $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());

            $controllerInstance->handle($routeTool->getControllerName(), $routeTool->getMethodName(), $routeTool->getParams());
        } catch (\Throwable $e) {
            try {
                //The errors here will be handed over to the ErrorController
                $controllerInstance = $this->getController($this->routeConfig->getErrorControllerName());
                $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());
                $controllerInstance->onExceptionHandle($e);
            } catch (\Throwable $e) {
                $this->warn($e);
            }
        }
    }
}
