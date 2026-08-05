<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Multicast;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Ipc\GetIpc;

/**
 * Encapsulates the multicast (publish/subscribe) capability of an Actor.
 *
 * Held by {@see \Yew\Plugins\Actor\Actor}, invoked via $this->multicast->publish(...).
 */
class Multicast
{
    use GetIpc;
    use GetLogger;

    /**
     * Create a multicast facade for one actor.
     *
     * @param string $actorName Owning actor name
     * @param MulticastConfig $config Multicast plugin config
     */
    public function __construct(
        private string $actorName,
        private MulticastConfig $config
    ) {
    }

    /**
     * Cached IPC proxy for the multicast channel (reused within the same instance).
     *
     * @var Channel|null
     */
    private ?Channel $channel = null;

    /**
     * Subscribe to a channel.
     *
     * @param string $channel
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function subscribe(string $channel)
    {
        $this->channel()->subscribe($channel, $this->actorName);
    }

    /**
     * Unsubscribe from a channel.
     *
     * @param string $channel
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function unsubscribe(string $channel)
    {
        $this->channel()->unsubscribe($channel, $this->actorName);
    }

    /**
     * Unsubscribe the Actor from all channels.
     *
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function unsubscribeAll(): void
    {
        $this->channel()->unsubscribeAll($this->actorName);
    }

    /**
     * Whether the Actor has subscribed to a given channel (requires a return value, so a non-oneway proxy is used).
     *
     * @param string $channel
     * @return bool
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function hasChannel(string $channel): bool
    {
        /** @var Channel $proxy */
        $proxy = $this->callProcessName($this->config->getProcessName(), Channel::class);

        return $proxy->hasChannel($channel, $this->actorName);
    }

    /**
     * Publish a message to a channel, excluding the Actor itself by default.
     *
     * @param string $channel
     * @param string $message
     * @param array $excludeActorList
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function publish(string $channel, string $message, array $excludeActorList = []): void
    {
        if (empty($excludeActorList)) {
            $excludeActorList = [$this->actorName];
        }

        $this->channel()->publish($channel, $message, $excludeActorList, $this->actorName);
        $this->broadcastToCluster($channel, $message);
    }

    /**
     * Publish a message to a channel, delivered only to other subscribers (excluding the Actor itself).
     *
     * @param string $channel
     * @param string $message
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function publishTo(string $channel, string $message): void
    {
        $this->channel()->publish($channel, $message, [$this->actorName], $this->actorName);
        $this->broadcastToCluster($channel, $message);
    }

    /**
     * Publish a message to a channel, including the Actor itself.
     *
     * @param string $channel
     * @param string $message
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function publishIn(string $channel, string $message)
    {
        $this->channel()->publish($channel, $message, [], $this->actorName);
        $this->broadcastToCluster($channel, $message);
    }

    /**
     * Fan the message out to the other cluster nodes (no-op unless cluster mode is enabled and a
     * broadcaster has been injected by MulticastPlugin).
     *
     * @param string $channel
     * @param string $message
     * @return void
     */
    protected function broadcastToCluster(string $channel, string $message): void
    {
        $broadcaster = $this->config->getBroadcaster();
        if ($broadcaster === null) {
            return;
        }
        try {
            $broadcaster->broadcast($channel, $message);
        } catch (\Throwable $e) {
            // Never let a cluster fan-out failure break local publishing.
            $this->error(sprintf('[Multicast] cluster broadcast failed: %s', $e->getMessage()));
        }
    }

    /**
     * Get the IPC proxy for the multicast Channel (lazily loaded and cached for reuse).
     *
     * @return Channel
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    private function channel(): Channel
    {
        if ($this->channel === null) {
            $this->channel = $this->callProcessName($this->config->getProcessName(), Channel::class, true);
        }

        return $this->channel;
    }
}
