<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Whoops\Aspect;

use Yew\Core\Server\Beans\Response;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Whoops\WhoopsConfig;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Around;
use Whoops\Run;


class WhoopsAspect extends OrderAspect
{
    /**
     * @var Run
     */
    private Run $run;

    /**
     * @var WhoopsConfig
     */
    protected WhoopsConfig $whoopsConfig;

    public function __construct(Run $run, WhoopsConfig $whoopsConfig)
    {
        $this->run = $run;
        $this->whoopsConfig = $whoopsConfig;
    }

    /**
     * Around onHttpRequest
     *
     * @param MethodInvocation $invocation Invocation
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onHttpRequest(*))")
     * @return mixed|null
     * @throws \Throwable
     */
    protected function aroundRequest(MethodInvocation $invocation)
    {
        /**
         * @var $response Response
         */
        list($request, $response) = $invocation->getArguments();
        $result = null;
        try {
            $result = $invocation->proceed();
        } catch (\Throwable $e) {
            setContextValue("lastException", $e);
        }
        $e = getContextValue("lastException");
        if ($e != null && $this->whoopsConfig->isEnable() && Server::$instance->getServerConfig()->isDebug()) {
            $response->withContent($this->run->handleException($e));
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "WhoopsAspect";
    }
}