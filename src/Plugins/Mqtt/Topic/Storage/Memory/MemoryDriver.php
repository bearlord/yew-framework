<?php

namespace Yew\Plugins\Mqtt\Topic\Storage\Memory;

use Yew\Core\DI\DI;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Plugins\Mqtt\Topic\Storage\DriverInterface;

/**
 * In-memory topic subscription driver backed by Swoole cross-process shared Table.
 *
 * Provides fast read/write for topic-subscriber mappings at runtime.
 * Data is not persisted and will be lost on server restart.
 *
 * Local copy of Yew\Plugins\Topic\Storage\Memory\MemoryDriver so the Mqtt plugin
 * does not depend on the Topic plugin.
 */
class MemoryDriver implements DriverInterface
{
    /**
     * @var string The storage driver type identifier
     */
    protected string $type = "memory";

    /**
     * @var Table The shared topicTable instance
     */
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
     * Get the storage driver type identifier
     *
     * @return string The driver type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Build a unique key from topic and client_id for table storage
     *
     * @param string $topic The topic pattern
     * @param string $clientId The subscriber client_id
     * @return string The composite key
     */
    protected function buildKey(string $topic, string $clientId)
    {
        return sprintf("%s%s", $topic, $clientId);
    }

    /**
     * Add a subscription for a client_id to a topic
     *
     * @param string $topic The topic pattern to subscribe to
     * @param string $clientId The subscriber client_id
     * @return bool True on success
     */
    public function addSubscription(string $topic, string $clientId): bool
    {
        $key = $this->buildKey($topic, $clientId);

        $this->topicTable->set($key, [
            "topic" => $topic,
            "client_id" => $clientId
        ]);

        return true;
    }

    /**
     * Remove a subscription for a client_id from a topic
     *
     * @param string $topic The topic pattern to unsubscribe from
     * @param string $clientId The subscriber client_id
     * @return bool True on success
     */
    public function removeSubscription(string $topic, string $clientId): bool
    {
        $key = $this->buildKey($topic, $clientId);
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
     * Retrieve all stored items
     *
     * @return array|null All items or null if not implemented
     */
    public function allItems(): ?array
    {
        return null;
    }

    /**
     * Retrieve a batch of items
     *
     * @param int $limit The number of items to retrieve
     * @return array|null A batch of items or null if not implemented
     */
    public function batchItems(int $limit = 50, int $offset = 0): ?array
    {
        return null;
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
     * @return array|null List of subscriber client_ids or null if not implemented
     */
    public function getSubscribers(string $topic): ?array
    {
        return null;
    }

    /**
     * Get all topic subscriptions for a given client_id
     *
     * @param string $clientId The subscriber client_id
     * @return array|null List of topic patterns or null if not implemented
     */
    public function getSubscriptions(string $clientId): ?array
    {
        return null;
    }


}
