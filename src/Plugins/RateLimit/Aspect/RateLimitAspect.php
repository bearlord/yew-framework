<?php

namespace Yew\Plugins\RateLimit\Aspect;

use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Coroutine\Coroutine;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Helpers\Json;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\After;
use Yew\Goaop\Lang\Annotation\Around;
use Yew\Goaop\Lang\Annotation\Before;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\RateLimit\Annotation\RateLimit;
use Yew\Plugins\RateLimit\Exception\RateLimitException;
use Yew\Plugins\RateLimit\Handle\RateLimitHandler;
use Yew\Plugins\Route\RoutePlugin;
use Yew\TokenBucket\Storage\StorageException;

class RateLimitAspect extends OrderAspect
{

    /**
     * @var array
     */
    private array $config;

    /**
     * @var RateLimitHandler
     */
    private RateLimitHandler $rateLimitHandler;


    public function __construct()
    {
        $this->rateLimitHandler = new RateLimitHandler();

    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'RateLimit';
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
                        case ($annotation instanceof RateLimit):
                            $bucketKey = $annotation->key;

                            if (is_array($bucketKey) && count($bucketKey) == 2) {
                                $bucketKey = call_user_func([$bucketKey[0], $bucketKey[1]], $invocation);
                            }

                            if (!$bucketKey) {
                                $bucketKey = $clientData->getPath();
                            }

                            $bucket = $this->rateLimitHandler->build($bucketKey, $annotation->create, $annotation->capacity, $annotation->waitTimeout);

                            $maxTime = microtime(true) + $annotation->waitTimeout;
                            $seconds = 0;

                            while (true) {
                                try {
                                    if ($bucket->consume($annotation->consume, $seconds)) {
                                        return $invocation->proceed();
                                    }
                                } catch (StorageException $exception) {
                                    throw $exception;
                                }

                                if (microtime(true) + $seconds > $maxTime) {
                                    break;
                                }
                                Coroutine::sleep(max($seconds, 0.001));
                            }

                            if (empty($annotation->limitCallback)
                                || !is_array($annotation->limitCallback)
                                || count($annotation->limitCallback) != 2 ) {
                                throw new RateLimitException('Service Unavailable.', 503);
                            }

                            $callResult = call_user_func([$annotation->limitCallback[0], $annotation->limitCallback[1]], $seconds);

                            $clientData = getContextValueByClassName(ClientData::class);

                            $clientData->getResponse()->withHeader("Content-Type", "application/json");
                            $clientData->getResponse()->withContent(Json::encode($callResult))->end();

                            break;

                        default:
                            return $invocation->proceed();

                    }
                }
                break;
        }
    }


}