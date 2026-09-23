<?php

namespace Yew\Plugins\Mqtt\Topic\Storage;

/**
 * Interface for topic subscription storage drivers.
 *
 * Defines the contract for persisting and retrieving topic-subscriber relationships.
 *
 * Local copy of Yew\Plugins\Topic\Storage\DriverInterface so the Mqtt plugin
 * does not depend on the Topic plugin.
 */
interface DriverInterface
{
    /**
     * Initialize the storage driver
     */
    public function init();

    /**
     * Add a subscription for a client_id to a topic
     *
     * @param string $topic The topic pattern to subscribe to
     * @param string $clientId The client_id of the subscriber
     * @return bool True on success, false on failure
     */
    public function addSubscription(string $topic, string $clientId): bool;

    /**
     * Remove a subscription for a client_id from a topic
     *
     * @param string $topic The topic pattern to unsubscribe from
     * @param string $clientId The client_id of the subscriber
     * @return bool True on success, false on failure
     */
    public function removeSubscription(string $topic, string $clientId): bool;

    /**
     * Delete all subscriptions for a topic
     *
     * @param string $topic The topic pattern whose subscribers should be removed
     * @return bool True on success, false on failure
     */
    public function deleteTopic(string $topic): bool;

    /**
     * Get all items
     *
     * @return array|null All items or null if not available
     */
    public function allItems(): ?array;

    /**
     * Get a batch of items
     *
     * @param int $limit The number of items to retrieve
     * @param int $offset The number of items to skip (for pagination)
     * @return array|null A batch of items or null if not available
     */
    public function batchItems(int $limit = 50, int $offset = 0): ?array;

    /**
     * Get all subscriptions
     *
     * @return array|null All subscriptions grouped by topic, or null if not available
     */
    public function allSubscriptions(): ?array;

    /**
     * Retrieve all stored subscriptions
     *
     * @return array|null All subscriptions grouped by topic, or null if not available
     */
    public function allSubscribers(): ?array;

    /**
     * Get all subscribers for a given topic
     *
     * @param string $topic The topic pattern to look up
     * @return array List of subscriber client_ids subscribed to the topic
     */
    public function getSubscribers(string $topic): ?array;

    /**
     * Get all topic subscriptions for a given client_id
     *
     * @param string $clientId The client_id of the subscriber
     * @return array List of topic patterns the client_id is subscribed to
     */
    public function getSubscriptions(string $clientId): ?array;

    /**
     * Get the type of the storage driver
     *
     * @return string The type of the storage driver
     */
    public function getType(): string;
}
