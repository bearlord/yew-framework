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
	 * Cached Topic plugin configuration (process name, etc.).
	 * @var TopicConfig|null
	 */
	protected ?TopicConfig $topicConfig = null;

    /**
     * Lazily resolve and return the TopicConfig instance from the DI container.
     *
     * @return TopicConfig|null The resolved config, or null if not registered.
     */
    protected function getTopicConfig()
    {
        if ($this->topicConfig == null) {
            $this->topicConfig = DIGet(TopicConfig::class);
        }
        return $this->topicConfig;
    }

	/**
	 * Check whether a uid is subscribed to a given topic.
	 *
	 * Forwards the call to the remote Topic process via IPC.
	 *
	 * @param string $topic Subscription topic.
	 * @param string $uid Subscriber unique id.
	 * @return bool True if the uid is subscribed.
	 */
	public function hasTopic(string $topic, string $uid): bool
	{
		if (empty($uid)) {
			return false;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class);
		if (empty($ipcProxy)) {
			return false;
		}

		return $ipcProxy->hasTopic($topic, $uid);
	}

	/**
	 * Delete an entire topic (and all its subscriber records) on the Topic process.
	 *
	 * @param string $topic Topic to delete.
	 * @return bool True on success.
	 */
	public function deleteTopic(string $topic): bool
	{
		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		if (empty($ipcProxy)) {
			return false;
		}

		return $ipcProxy->deleteTopic($topic);
	}

    /**
     * Get all subscriber uids for a topic (including wildcard matches) from
     * the remote Topic process.
     *
     * @param string $topic Topic to resolve subscribers for.
     * @return array List of subscriber uids (empty if none / proxy missing).
     */
    public function getSubscribers(string $topic): array
    {
        /** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		if (empty($ipcProxy)) {
			return [];
		}
        return $ipcProxy->getSubscribers($topic);

    }

	/**
	 * Add a subscription for a uid to a topic on the remote Topic process.
	 *
	 * @param string $topic Subscription topic.
	 * @param string $uid Subscriber unique id.
	 * @return bool True when the request was dispatched to the Topic process.
	 */
	public function addSubscription(string $topic, string $uid): bool
	{
		if (empty($uid)) {
			return false;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);

		if (empty($ipcProxy)) {
			return false;
		}

		$ipcProxy->addSubscription($topic, $uid);

        return true;
	}

	/**
	 * Remove a single uid's subscription from a topic on the remote Topic process.
	 *
	 * @param string $topic Subscription topic.
	 * @param string $uid Subscriber unique id.
	 * @return void
	 */
	public function removeSubscription(string $topic, string $uid)
	{
		if (empty($uid)) {
			return;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->removeSubscription($topic, $uid);
	}

	/**
	 * Clear all subscriptions of the uid bound to the given connection fd on
	 * the remote Topic process.
	 *
	 * @param int $fd Connection file descriptor.
	 * @return void
	 */
	public function clearFdSubscription(int $fd)
	{
		if (empty($fd)) {
			return;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->clearFdSubscription($fd);
	}

	/**
	 * Clear (remove) all subscriptions of a given uid across every topic on
	 * the remote Topic process.
	 *
	 * @param string $uid Subscriber unique id.
	 * @return void
	 */
	public function clearUidSubbscription(string $uid)
	{
		if (empty($uid)) {
			return;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->clearUidSubbscription($uid);
	}

	/**
	 * Publish data to every subscriber of a topic on the remote Topic process.
	 *
	 * @param string $topic Topic to publish to.
	 * @param mixed $data Payload to deliver.
	 * @param array|null $excludeUidList Uids to skip (e.g. the sender).
	 * @return void
	 */
	public function publish(string $topic, $data, ?array $excludeUidList = null)
	{
		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->publish($topic, $data, $excludeUidList);
	}
}
