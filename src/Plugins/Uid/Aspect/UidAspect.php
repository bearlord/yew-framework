<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Uid\Aspect;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Uid\UidBean;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\After;

class UidAspect extends OrderAspect
{
    use GetLogger;

    /**
     * @var UidBean
     */
    protected UidBean $uid;

    /**
     * After onTcpReceive
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpClose(*))")
     */
    protected function afterTcpClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        Server::$instance->getContainer()->get(UidBean::class)->unBindUid($fd);
    }

    /**
     * After onWsClose
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @After("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsClose(*))")
     */
    protected function afterWsClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();
        Server::$instance->getContainer()->get(UidBean::class)->unBindUid($fd);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "UidAspect";
    }
}