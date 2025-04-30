<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic\Aspect;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Topic\GetTopic;
use Yew\Plugins\Uid\Aspect\UidAspect;
use Yew\Plugins\Uid\GetUid;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Before;

class TopicAspect extends OrderAspect
{
    use GetLogger;
    use GetTopic;
    use GetUid;

    public function __construct()
    {
        //To be executed before UidAspect, otherwise the uid will be cleared
        $this->atBefore(UidAspect::class);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "TopicAspect";
    }

    /**
     * around onTcpReceive
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Before("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onTcpClose(*))")
     */
    protected function afterTcpClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();

        //This is a cross-process call, so use uid instead of fd to avoid timing errors
        $uid = $this->getFdUid($fd);
        if ($uid != null) {
            $this->clearUidSub($uid);
        }
    }

    /**
     * Around onTcpReceive
     *
     * @param MethodInvocation $invocation Invocation
     * @throws \Throwable
     * @Before("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onWsClose(*))")
     */
    protected function afterWsClose(MethodInvocation $invocation)
    {
        list($fd, $reactorId) = $invocation->getArguments();
        $this->clearFdSub($fd);
    }
}