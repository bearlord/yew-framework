<?php

namespace Yew\Plugins\Topic\Storage;

/**
 * Interface for topic subscription storage drivers.
 *
 * Defines the contract for persisting and retrieving topic-subscriber relationships.
 */
interface DriverInterface
{
    /**
     * Initialize the storage driver
     */
    public function init();

    /**
     * Add a subscription for a uid to a topic
     *
     * @param string $topic The topic pattern to subscribe to
     * @param string $uid The unique identifier of the subscriber
     * @return bool True on success, false on failure
     */
    public function addSubscription(string $topic, string $uid): bool;

    /**
     * Remove a subscription for a uid from a topic
     *
     * @param string $topic The topic pattern to unsubscribe from
     * @param string $uid The unique identifier of the subscriber
     * @return bool True on success, false on failure
     */
    public function removeSubscription(string $topic, string $uid): bool;

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
     * @return array List of subscriber uids subscribed to the topic
     */
    public function getSubscribers(string $topic): ?array;

    /**
     * Get all topic subscriptions for a given uid
     *
     * @param int $uid The unique identifier of the subscriber
     * @return array List of topic patterns the uid is subscribed to
     */
    public function getSubscriptions(int $uid): ?array;

    /**
     * Get the type of the storage driver
     *
     * @return string The type of the storage driver
     */
    public function getType(): string;
}