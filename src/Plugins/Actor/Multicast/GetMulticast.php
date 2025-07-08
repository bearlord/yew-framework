<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Multicast;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Ipc\GetIpc;

trait GetMulticast
{
    use GetIpc;
    use GetLogger;

    /**
     * @var MulticastConfig
     */
    protected $multicastConfig;

    /**
     * @return MulticastConfig|mixed
     * @throws \Exception
     */
    protected function getMulticastConfig(): MulticastConfig
    {
        if ($this->multicastConfig == null) {
            $this->multicastConfig = DIGet(MulticastConfig::class);
        }

        return $this->multicastConfig;
    }

    /**
     * Has channel
     * @param string $channel
     * @param string $actor
     * @return bool
     * @throws \Exception
     */
    public function actorHasChannel(string $channel, string $actor): bool
    {
        if (empty($actor)) {
            $this->warn("Actor is empty");
            return false;
        }

        /** @var Channel $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), Channel::class);
        return $rpcProxy->hasChannel($channel, $actor);
    }

    /**
     * Delete channel
     *
     * @param string $channel
     * @throws \Exception
     */
    public function deleteChannel(string $channel)
    {
        /** @var Channel $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), Channel::class, true);
        $rpcProxy->deleteChannel($channel);
    }

    /**
     * Subscribe
     *
     * @param string $channel
     * @param string $actor
     * @throws \Exception
     */
    public function actorSubscribe(string $channel, string $actor)
    {
        if (empty($actor)) {
            $this->warn("Actor is empty");
            return;
        }

        /** @var Channel $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), Channel::class, true);
        $rpcProxy->subscribe($channel, $actor);
    }

    /**
     * Unsubscribe
     *
     * @param string $channel
     * @param string $actor
     * @throws \Exception
     */
    public function actorUnsubscribe(string $channel, string $actor)
    {
        if (empty($actor)) {
            $this->warn("Actor is empty");
            return;
        }

        /** @var Channel $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), Channel::class, true);
        $rpcProxy->unsubscribe($channel, $actor);
    }

    /**
     * Unsubscribe all
     * @param string $actor
     * @throws \Exception
     */
    public function actorUnsubscribeAll(string $actor)
    {
        if (empty($actor)) {
            $this->warn("Actor is empty");
            return;
        }

        /** @var Channel $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), Channel::class, true);
        $rpcProxy->unsubscribeAll($actor);
    }

    /**
     * Publish subscription
     *
     * @param string $channel
     * @param string|null $message
     * @param array|null $excludeActorList
     */
    public function actorPublish(string $channel, ?string $message, ?array $excludeActorList = [])
    {
        /** @var Channel $rpcProxy */
        $rpcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), Channel::class, true);
        $rpcProxy->publish($channel, $message, $excludeActorList);
    }
}
