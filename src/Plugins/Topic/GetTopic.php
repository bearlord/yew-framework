<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic;

use DI\DependencyException;
use DI\NotFoundException;
use Yew\Core\Exception\Exception;
use Yew\Plugins\Ipc\GetIPc;
use Yew\Plugins\Ipc\IpcException;

trait GetTopic
{
    use GetIPc;
    
    /**
     * @var TopicConfig|null
     */
    protected ?TopicConfig $topicConfig = null;

    /**
     * @param string $topic
     * @param string $uid
     * @return bool
     * @throws IpcException
     * @throws \Exception
     */
    public function hasTopic(string $topic, string $uid): bool
    {
        if (empty($uid)) {
            $this->warn("Uid is empty");
            return false;
        }

        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class);
        return $rpcProxy->hasTopic($topic, $uid);
    }

    /**
     * @param string $topic
     * @return void
     * @throws IpcException
     */
    public function delTopic(string $topic)
    {
        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->delTopic($topic);
    }

    /**
     * @return mixed|TopicConfig|null
     * @throws \Exception
     */
    protected function getTopicConfig()
    {
        if ($this->topicConfig == null) {
            $this->topicConfig = DIGet(TopicConfig::class);
        }
        return $this->topicConfig;
    }

    /**
     * @param string $topic
     * @param string $uid
     * @return void
     * @throws BadUTF8
     * @throws IpcException
     * @throws Exception
     * @throws \Exception
     */
    public function addSub(string $topic, string $uid)
    {
        if (empty($uid)) {
            $this->warn("Uid is empty");
            return;
        }

        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->addSub($topic, $uid);
    }

    /**
     * @param string $topic
     * @param string $uid
     * @return void
     * @throws IpcException
     * @throws \Exception
     */
    public function removeSub(string $topic, string $uid)
    {
        if (empty($uid)) {
            $this->warn("Uid is empty");
            return;
        }

        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->removeSub($topic, $uid);
    }

    /**
     * @param int $fd
     * @return void
     * @throws IpcException
     * @throws \Exception
     */
    public function clearFdSub(int $fd)
    {
        if (empty($fd)) {
            $this->warn("Fd is empty");
            return;
        }
        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->clearFdSub($fd);
    }

    /**
     * @param string $uid
     * @return void
     * @throws IpcException
     * @throws \Exception
     */
    public function clearUidSub(string $uid)
    {
        if (empty($uid)) {
            $this->warn("Uid is empty");
            return;
        }

        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->clearUidSub($uid);
    }

    /**
     * @param string $topic
     * @param $data
     * @param array|null $excludeUidList
     * @return void
     * @throws IpcException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function pub(string $topic, $data, ?array $excludeUidList = [])
    {
        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->pub($topic, $data, $excludeUidList);
    }
}
