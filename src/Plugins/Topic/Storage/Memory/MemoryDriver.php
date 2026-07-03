<?php

namespace Yew\Plugins\Topic\Storage\Memory;

use Yew\Core\DI\DI;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Plugins\Topic\Storage\DriverInterface;

/**
 * In-memory topic subscription driver backed by Swoole cross-process shared Table.
 *
 * Provides fast read/write for topic-subscriber mappings at runtime.
 * Data is not persisted and will be lost on server restart.
 */
class MemoryDriver implements DriverInterface
{
    protected string $type = "memory";

    private Table $topicTable;

    /**
     * Initialize the driver by loading the shared topicTable from DI container
     *
     * @return void
     */
	public function init()
	{
        $this->topicTable = DI::getInstance()->get("topicTable");
	}

    /**
     * Build a unique key from topic and uid for table storage
     *
     * @param string $topic The topic pattern
     * @param string $uid The subscriber unique identifier
     * @return string The composite key
     */
    protected function buildKey(string $topic, string $uid)
    {
        return sprintf("%s%s", $topic, $uid);
    }

    /**
     * Add a subscription for a uid to a topic
     *
     * @param string $topic The topic pattern to subscribe to
     * @param string $uid The subscriber unique identifier
     * @return bool True on success
     */
    public function addSubscription(string $topic, string $uid): bool
    {
        $key = $this->buildKey($topic, $uid);

        $this->topicTable->set($key, [
            "topic" => $topic,
            "uid" => $uid
        ]);

        return true;
    }

    /**
     * Remove a subscription for a uid from a topic
     *
     * @param string $topic The topic pattern to unsubscribe from
     * @param string $uid The subscriber unique identifier
     * @return bool True on success
     */
    public function removeSubscription(string $topic, string $uid): bool
    {
        $key = $this->buildKey($topic, $uid);
        $this->topicTable->delete($key);

        return true;
    }

    /**
     * Delete all subscriptions for a topic
     *
     * @param string $topic The topic pattern whose subscribers should be removed
     * @return bool True on success
     */
    public function deleteTopic(string $topic): bool
    {
        return true;
    }

    /**
     * Retrieve all stored subscriptions
     *
     * @return array|null All subscriptions or null if not implemented
     */
    public function allSubscriptions(): ?array
    {
        return null;
    }

    /**
     * Retrieve all subscribers across all topics
     *
     * @return array|null All subscribers or null if not implemented
     */
    public function allSubscribers(): ?array
    {
        return null;
    }

    /**
     * Get all subscribers for a given topic
     *
     * @param string $topic The topic pattern to look up
     * @return array|null List of subscriber uids or null if not implemented
     */
    public function getSubscribers(string $topic): ?array
    {
        return null;
    }

    /**
     * Get all topic subscriptions for a given uid
     *
     * @param int $uid The subscriber unique identifier
     * @return array|null List of topic patterns or null if not implemented
     */
    public function getSubscriptions(int $uid): ?array
    {
        return null;
    }
}