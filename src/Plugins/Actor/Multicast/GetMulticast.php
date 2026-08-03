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
    use GetLogger;

    /**
     * @var MulticastConfig|null
     */
    protected ?MulticastConfig $multicastConfig = null;

    /**
     * Lazily resolve the multicast config from the DI container.
     *
     * @return MulticastConfig
     */
    protected function getMulticastConfig(): MulticastConfig
    {
        if ($this->multicastConfig === null) {
            $this->multicastConfig = DIGet(MulticastConfig::class);
        }

        return $this->multicastConfig;
    }

    /**
     * Build a Multicast facade bound to the given actor name.
     *
     * @param string $actor
     * @return Multicast
     */
    protected function multicastFor(string $actor): Multicast
    {
        return new Multicast($actor, $this->getMulticastConfig());
    }

    /**
     * Whether the actor has subscribed to the channel.
     *
     * @param string $channel
     * @param string $actor
     * @return bool
     */
    public function actorHasChannel(string $channel, string $actor): bool
    {
        if ($actor === '') {
            $this->warn("Actor is empty");

            return false;
        }

        return $this->multicastFor($actor)->hasChannel($channel);
    }

    /**
     * Delete a channel and all its subscriptions.
     *
     * @param string $channel
     * @return void
     */
    public function deleteChannel(string $channel): void
    {
        $this->multicastFor('')->deleteChannel($channel);
    }

    /**
     * Subscribe an actor to a channel.
     *
     * @param string $channel
     * @param string $actor
     * @return void
     */
    public function actorSubscribe(string $channel, string $actor): void
    {
        if ($actor === '') {
            $this->warn("Actor is empty");

            return;
        }

        $this->multicastFor($actor)->subscribe($channel);
    }

    /**
     * Unsubscribe an actor from a channel.
     *
     * @param string $channel
     * @param string $actor
     * @return void
     */
    public function actorUnsubscribe(string $channel, string $actor): void
    {
        if ($actor === '') {
            $this->warn("Actor is empty");

            return;
        }

        $this->multicastFor($actor)->unsubscribe($channel);
    }

    /**
     * Unsubscribe an actor from all channels.
     *
     * @param string $actor
     * @return void
     */
    public function actorUnsubscribeAll(string $actor): void
    {
        if ($actor === '') {
            $this->warn("Actor is empty");

            return;
        }

        $this->multicastFor($actor)->unsubscribeAll();
    }

    /**
     * Publish a message to a channel on behalf of an actor.
     *
     * @param string $channel
     * @param string|null $message
     * @param array $excludeActorList
     * @return void
     */
    public function actorPublish(string $channel, ?string $message, array $excludeActorList = []): void
    {
        $this->multicastFor('')->publish($channel, (string) $message, $excludeActorList);
    }
}
