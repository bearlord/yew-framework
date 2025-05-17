<?php

namespace Yew\Plugins\RateLimit\Aspect;

use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Coroutine\Server\Server;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\After;
use Yew\Goaop\Lang\Annotation\Around;
use Yew\Goaop\Lang\Annotation\Before;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\RateLimit\Annotation\RateLimit;
use Yew\Plugins\Route\RoutePlugin;

class RateLimitAspect extends OrderAspect
{


    public function getName(): string
    {
        return 'RateLimit';
    }

    /**
     * before onHttpRequest
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onHttpRequest(*))")
     */
    protected function aroundHttpRequest(MethodInvocation $invocation)
    {
        /** @var ClientData $clientData */
        $clientData = getContextValueByClassName(ClientData::class);
        //Port
        $port = $clientData->getClientInfo()->getServerPort();
        //Request method
        $requestMethod = strtoupper($clientData->getRequestMethod());

        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $port, $requestMethod), $clientData->getPath());

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:

                $handler = $routeInfo[1];
                $clientData->setControllerName($handler[0]->name);
                $clientData->setMethodName($handler[1]->name);
                $methodReflection = $handler[1]->getReflectionMethod();
                $annotations = RoutePlugin::$instance->getScanClass()->getMethodAndInterfaceAnnotations($methodReflection);
                $clientData->setAnnotations($annotations);


                foreach ($annotations as $annotation) {
                    switch (true) {
                        case ($annotation instanceof RateLimit):
                            //todo
                            var_dump($annotation);
                            break;
                        default:
                            break;
                    }
                }

                break;
        }

        $invocation->proceed();
        return;
    }


}