<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic;

use DI\DependencyException;
use DI\NotFoundException;
use Yew\Core\Exception\Exception;
use Yew\Plugins\Ipc\GetIpc;
use Yew\Plugins\Ipc\IpcException;

trait GetTopic
{
    use GetIpc;
    
    /**
     * @var TopicConfig|null
     */
    protected ?TopicConfig $topicConfig = null;

    /**
     * @param string $topic
     * @param string $uid
     * @return bool
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
     */
    public function deleteTopic(string $topic)
    {
        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->deleteTopic($topic);
    }

    /**
     * @return mixed|TopicConfig|null
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
     */
    public function addSubscription(string $topic, string $uid)
    {
        if (empty($uid)) {
            $this->warn("Uid is empty");
            return;
        }

        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->addSubscription($topic, $uid);
    }

    /**
     * @param string $topic
     * @param string $uid
     * @return void
     */
    public function removeSubscription(string $topic, string $uid)
    {
        if (empty($uid)) {
            $this->warn("Uid is empty");
            return;
        }

        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->removeSubscription($topic, $uid);
    }

    /**
     * @param int $fd
     * @return void
     */
    public function clearFdSubscription(int $fd)
    {
        if (empty($fd)) {
            $this->warn("Fd is empty");
            return;
        }
        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->clearFdSubscription($fd);
    }

    /**
     * @param string $uid
     * @return void
     */
    public function clearUidSubbscription(string $uid)
    {
        if (empty($uid)) {
            $this->warn("Uid is empty");
            return;
        }

        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->clearUidSubbscription($uid);
    }

    /**
     * @param string $topic
     * @param $data
     * @param array|null $excludeUidList
     * @return void
     */
    public function publish(string $topic, $data, ?array $excludeUidList = [])
    {
        /** @var Topic $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
        $rpcProxy->publish($topic, $data, $excludeUidList);
    }
}
