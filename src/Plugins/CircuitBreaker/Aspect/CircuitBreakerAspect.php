<?php
namespace Yew\Plugins\CircuitBreaker\Aspect;

use Yew\Coroutine\Coroutine;
use Yew\Framework\Helpers\Json;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\After;
use Yew\Goaop\Lang\Annotation\Around;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker;
use Yew\Plugins\CircuitBreaker\Handler\HandlerInterface;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Route\RoutePlugin;
use Yew\TokenBucket\Storage\StorageException;
use Yew\Yew;

class CircuitBreakerAspect extends OrderAspect
{
    /**
     * @var array
     */
    private array $config;

    public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'CircuitBreaker';
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
                        case ($annotation instanceof CircuitBreaker):
                            if (!Yew::getContainer()->hasSingleton($annotation->handler)) {
                                Yew::getContainer()->setSingleton($annotation->handler, $annotation->handler);
                            }
                            $annotationHandleInstance = Yew::getContainer()->get($annotation->handler);

                            if (!$annotationHandleInstance || (!$annotationHandleInstance instanceof HandlerInterface)) {
                                return $invocation->proceed();
                            }

                            $routeMethodName = sprintf("%s::%s", $handler[0]->name, $handler[1]->name);


                            $handleResult = $annotationHandleInstance->handle($routeMethodName, $invocation, $annotation);

                            if (!empty($handleResult)) {
                                $clientData = getContextValueByClassName(ClientData::class);

                                $clientData->getResponse()->withHeader("Content-Type", "application/json");
                                $clientData->getResponse()->withContent(Json::encode($handleResult))->end();
                            }
                            return $handleResult;

                            break;

                        default:
                            return $invocation->proceed();

                    }
                }
                break;
        }
    }
}