<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actuator\Aspect;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Beans\Request;
use Yew\Plugins\Actuator\ActuatorController;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\EasyRoute\Aspect\RouteAspect;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Around;

/**
 * Routes /actuator/* requests to ActuatorController, before the normal router.
 */
class ActuatorAspect extends OrderAspect
{
    use GetLogger;

    private ActuatorController $actuatorController;

    private Dispatcher $dispatcher;

    public function __construct(ActuatorController $actuatorController, Dispatcher $dispatcher)
    {
        $this->actuatorController = $actuatorController;
        $this->dispatcher = $dispatcher;
        $this->atBefore(RouteAspect::class);
    }

    /**
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onHttpRequest(*))")
     */
    protected function aroundRequest(MethodInvocation $invocation)
    {
        list($request, $response) = $invocation->getArguments();
        $routeInfo = $this->dispatcher->dispatch($request->getServer(Request::SERVER_REQUEST_METHOD), $request->getServer(Request::SERVER_REQUEST_URI));

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $invocation->proceed();

            case Dispatcher::METHOD_NOT_ALLOWED:
                $response->withStatus(405);
                $response->withHeader("Content-Type", "text/html; charset=utf-8");
                $response->withContent("不支持的请求方法");
                return null;

            case Dispatcher::FOUND:
                $method = $routeInfo[1];
                $vars = $routeInfo[2];
                $response->withHeader("Content-Type", "application/json; charset=utf-8");
                $response->withContent(call_user_func([$this->actuatorController, $method], $vars));
                return null;
        }

        return null;
    }

    public function getName(): string
    {
        return "ActuatorAspect";
    }
}
