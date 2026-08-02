<?php
/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Aspect;

use Yew\Core\Exception\Exception;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Route\Controller\IController;
use Yew\Plugins\Route\routePortConfig;
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

class RouteAspect extends OrderAspect
{
    use GetLogger;
    use GetBoostSend;

    /**
     * Per-port route configuration, keyed by listening port.
     * @var routePortConfig[]
     */
    protected array $routePortConfigs;

    /**
     * Resolved route tool instances, keyed by route tool class name.
     * A route tool parses raw client data into a controller/method/params tuple.
     * @var IRoute[]
     */
    protected array $routeTools = [];

    /**
     * Cached controller instances, keyed by controller class name.
     * @var IController[]
     */
    protected array $controllers = [];

    /**
     * Global route configuration (e.g. error controller name).
     * @var RouteConfig
     */
    protected RouteConfig $routeConfig;

    /**
     * Manager that runs the pre/route/post filter chains.
     * @var FilterManager
     */
    protected $filterManager;

    /**
     * RouteAspect constructor.
     *
     * Resolves and caches one route tool per port config, stores the global
     * route config and filter manager, and registers this aspect to run after
     * PackAspect (so the request is already unpacked).
     *
     * @param $routePortConfigs per-port route configurations
     * @param RouteConfig $routeConfig global route configuration
     * @throws \Exception
     */
    public function __construct($routePortConfigs, RouteConfig $routeConfig)
    {
        $this->routePortConfigs = $routePortConfigs;
        foreach ($this->routePortConfigs as $routePortConfig) {
            if (!isset($this->routeTools[$routePortConfig->getRouteTool()])) {
                $className = $routePortConfig->getRouteTool();
                $this->routeTools[$routePortConfig->getRouteTool()] = DIget($className);
            }
        }

        $this->routeConfig = $routeConfig;

        $this->filterManager = DIGet(FilterManager::class);

        $this->atAfter(PackAspect::class);
    }

    /**
     * Aspect name, used for ordering and debugging.
     *
     * @return string
     */
    public function getName(): string
    {
        return "RouteAspect";
    }

    /**
     * Around advice for HTTP requests.
     *
     * Resolves the route, dispatches to the matched controller, serialises the
     * result as JSON when needed and runs the filter chains. Any thrown
     * exception is forwarded to the configured error controller.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onHttpRequest(*))")
     */
    protected function aroundHttpRequest(MethodInvocation $invocation)
    {
        // Pick the route config bound to this port.
        $abstractServerPort = $invocation->getThis();
        $routePortConfig = $this->routePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("routePortConfig", $routePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        // Run the pre-filter chain; a filter may stop routing early.
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$routePortConfig->getRouteTool()];

        try {
            // Parse raw data into controller/method/params; stop if it fails.
            if (!$routeTool->handleClientData($clientData, $routePortConfig)) {
                return;
            }

            $controllerInstance = $this->getController($routeTool->getControllerName());
            if (empty($controllerInstance)) {
                $debug = Server::$instance->getConfigContext()->get("yew.server.debug");
                if ($debug) {
                    throw new Exception("Controller not Found");
                }
                $handleResult = "Path not Found";
                $clientData->setResponseRaw($handleResult);
            } else {
                $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());
                $handleResult = $controllerInstance->handle($routeTool->getControllerName(), $routeTool->getMethodName(), $routeTool->getParams());
                $clientData->setResponseRaw($handleResult);
            }

            // Run the route filter; it may stop before the response is sent.
            if ($this->filterManager->filter(AbstractFilter::FILTER_ROUTE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
                return;
            }

            // Serialise array/object responses to JSON.
            $responseRaw = $clientData->getResponseRaw();
            if (is_array($responseRaw) || is_object($responseRaw)) {
                $responseRaw = json_encode($responseRaw, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }

            $clientData->getResponse()->append($responseRaw);

            // Post-filter (e.g. finalise / log the response).
            $this->filterManager->filter(AbstractFilter::FILTER_PRO, $clientData);

        } catch (\Throwable $e) {
            // The errors here will be handed over to the IndexController
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
     * Resolve and cache a controller instance by class name.
     *
     * Returns null (instead of throwing) when the name is empty and debug mode
     * is off, so a missing route degrades to a "not found" response.
     *
     * @param $controllerName fully-qualified controller class name
     * @return IController|null
     * @throws RouteException when the class is missing or not an IController
     */
    private function getController($controllerName): ?IController
    {
        if (empty($controllerName)) {
            $debug = Server::$instance->getConfigContext()->get("yew.server.debug");
            if ($debug) {
                throw new RouteException("Controller name is null");
            }
            return null;
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
     * After-advice for TCP connect.
     *
     * Dispatches to a controller mapped to the "/onConnect" TCP route so the
     * application can run connection-start logic.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpConnect(*))")
     */
    protected function afterTcpConnect(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        $clientInfo = Server::$instance->getClientInfo($fd);
        $serverPort = $clientInfo->getServerPort();   // listening port
        $requestMethod = "TCP";                        // virtual method for TCP
        $onConnectPath = "/onConnect";                 // defined connect path
        // Match the "/onConnect" route for this port + method.
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onConnectPath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * Around-advice for TCP receive.
     *
     * Routes a TCP request the same way as HTTP: parse, dispatch to the
     * controller, apply the route filter, and auto-send the response when the
     * port is configured to do so.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpReceive(*))")
     */
    protected function aroundTcpReceive(MethodInvocation $invocation)
    {
        $abstractServerPort = $invocation->getThis();
        $routePortConfig = $this->routePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("routePortConfig", $routePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$routePortConfig->getRouteTool()];

        try {
            if (!$routeTool->handleClientData($clientData, $routePortConfig)) {
                return;
            }
            $controllerInstance = $this->getController($routeTool->getControllerName());
            $controllerInstance->initialization($routeTool->getControllerName(), $routeTool->getMethodName());

            $clientData->setResponseRaw($controllerInstance->handle($routeTool->getControllerName(), $routeTool->getMethodName(), $routeTool->getParams()));
            if ($this->filterManager->filter(AbstractFilter::FILTER_ROUTE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
                return;
            }
            // Auto-reply with the controller return value if the port allows it.
            if ($routePortConfig->getAutoSendReturnValue()) {
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
     * Before-advice for TCP close.
     *
     * Caches the client info snapshot (so it survives the disconnect) and then
     * dispatches to the "/beforeClose" TCP route for pre-close logic.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Before("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpClose(*))")
     */
    protected function beforeTcpClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        // Snapshot client info before Swoole releases the fd.
        Server::$instance->setClientInfoSnapshot(Server::$instance->getServer()->getClientInfo($fd));

        $clientInfo = Server::$instance->getClientInfo($fd);
        $serverPort = $clientInfo->getServerPort();   // listening port
        $requestMethod = "TCP";                        // virtual method for TCP
        $onClosePath = "/beforeClose";                 // defined pre-close path
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * After-advice for TCP close.
     *
     * Caches the client info snapshot and dispatches to the "/onClose" TCP
     * route for post-close cleanup.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpClose(*))")
     */
    protected function afterTcpClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        // Snapshot client info before Swoole releases the fd.
        Server::$instance->setClientInfoSnapshot(Server::$instance->getServer()->getClientInfo($fd));

        $clientInfo = Server::$instance->getClientInfo($fd);
        $serverPort = $clientInfo->getServerPort();   // listening port
        $requestMethod = "TCP";                        // virtual method for TCP
        $onClosePath = "/onClose";                     // defined close path
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * After-advice for WebSocket open.
     *
     * Dispatches to the "/onWsOpen" WS route so the application can handle a
     * newly established WebSocket connection.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsOpen(*))")
     */
    protected function afterWsOpen(MethodInvocation $invocation)
    {
        $request = $invocation->getArguments()[0];
        $fd = $request->getFd();                       // connection fd
        $clientInfo = Server::$instance->getClientInfo($fd);
        $reactorId = $clientInfo->getReactorId();      // worker reactor id
        $serverPort = $clientInfo->getServerPort();    // listening port
        $requestMethod = "WS";                         // virtual method for WS
        $onConnectPath = "/onWsOpen";                  // defined open path
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onConnectPath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId, $request]);
    }

    /**
     * Around-advice for WebSocket message.
     *
     * Routes an incoming WebSocket frame: parse, resolve controller/method,
     * attach the ClientData, dispatch, apply the route filter and optionally
     * auto-send the response.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsMessage(*))")
     */
    protected function aroundWsMessage(MethodInvocation $invocation)
    {
        $abstractServerPort = $invocation->getThis();
        $routePortConfig = $this->routePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("routePortConfig", $routePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$routePortConfig->getRouteTool()];

        try {
            if (!$routeTool->handleClientData($clientData, $routePortConfig)) {
                return;
            }

			$_controllerName = $routeTool->getControllerName();
			$_methodName = $routeTool->getMethodName();
			$_params = $routeTool->getParams();

            // Guard against empty route results to avoid a fatal dispatch.
            if (empty($_controllerName) || empty($_methodName)) {
                $this->warn("Controller or method name is empty. Path:" . $clientData->getPath());
                return;
            }

            $controllerInstance = $this->getController($_controllerName);

			// Inject the parsed request data into the controller.
			$controllerInstance->setClientData($clientData);

            $controllerInstance->initialization($_controllerName, $_methodName);

            $clientData->setResponseRaw($controllerInstance->handle($_controllerName, $_methodName, $_params));

            if ($this->filterManager->filter(AbstractFilter::FILTER_ROUTE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
                return;
            }
            // Auto-reply with the controller return value if the port allows it.
            if ($routePortConfig->getAutoSendReturnValue()) {
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
     * Before-advice for WebSocket close.
     *
     * Caches the client info snapshot (so it survives the disconnect) and then
     * dispatches to the "/beforeWsClose" WS route for pre-close logic.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Before("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsClose(*))")
     */
    protected function beforeWSClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        // Snapshot client info before Swoole releases the fd.
        Server::$instance->setClientInfoSnapshot(Server::$instance->getServer()->getClientInfo($fd));

        $clientInfo = Server::$instance->getClientInfo($fd);
        $serverPort = $clientInfo->getServerPort();   // listening port
        $requestMethod = "WS";                         // virtual method for WS
        $onClosePath = "/beforeWsClose";               // defined pre-close path
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * After-advice for WebSocket close.
     *
     * Caches the client info snapshot and dispatches to the "/onWsClose" WS
     * route for post-close cleanup.
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsClose(*))")
     */
    protected function afterWSClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        // Snapshot client info before Swoole releases the fd.
        Server::$instance->setClientInfoSnapshot(Server::$instance->getServer()->getClientInfo($fd));

        $clientInfo = Server::$instance->getClientInfo($fd);
        $serverPort = $clientInfo->getServerPort();   // listening port
        $requestMethod = "WS";                         // virtual method for WS
        $onClosePath = "/onWsClose";                   // defined close path
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $serverPort, $requestMethod), $onClosePath);

        if ($routeInfo[0] !== Dispatcher::FOUND) {
            return;
        }

        $instance = new $routeInfo[1][0]->name();
        call_user_func_array([$instance, $routeInfo[1][1]->name], [$fd, $reactorId]);
    }

    /**
     * Around-advice for UDP packet.
     *
     * Routes a UDP datagram to its controller. UDP is connectionless, so the
     * response is normally sent back via sendto rather than auto-pushed.
     *
     * @param MethodInvocation $invocation Invocation
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onUdpPacket(*))")
     * @throws \Throwable
     */
    protected function aroundUdpPacket(MethodInvocation $invocation)
    {
        $abstractServerPort = $invocation->getThis();
        $routePortConfig = $this->routePortConfigs[$abstractServerPort->getPortConfig()->getPort()];
        setContextValue("routePortConfig", $routePortConfig);

        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        if ($clientData == null) {
            return;
        }
        if ($this->filterManager->filter(AbstractFilter::FILTER_PRE, $clientData) == AbstractFilter::RETURN_END_ROUTE) {
            return;
        }
        $routeTool = $this->routeTools[$routePortConfig->getRouteTool()];
        try {
            if (!$routeTool->handleClientData($clientData, $routePortConfig)) {
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
