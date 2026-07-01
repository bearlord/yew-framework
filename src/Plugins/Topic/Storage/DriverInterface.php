<?php

namespace Yew\Plugins\Topic\Storage;

interface DriverInterface
{
    /**
     * Add a subscription for a uid to a topic
     *
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function addSubscription(string $topic, string $uid): bool;

    /**
     * Remove a subscription for a uid from a topic
     *
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function removeSubscription(string $topic, string $uid): bool;

    /**
     * Check if a uid has subscribed to a topic
     *
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function hasTopic(string $topic, string $uid): bool;

    /**
     * Delete all subscriptions for a topic
     *
     * @param string $topic
     * @return bool
     */
    public function deleteTopic(string $topic): bool;

    /**
     * Clear all subscriptions for a fd
     *
     * @param int $fd
     * @return bool
     */
    public function clearFdSubbscription(int $fd): bool;

    /**
     * Clear all subscriptions for a uid
     *
     * @param string $uid
     * @return bool
     */
    public function clearUidSubbscription(string $uid): bool;

    /**
     * Publish data to all subscribers of a topic
     *
     * @param string $topic
     * @param mixed $data
     * @param array|null $excludeUidList
     * @return bool
     */
    public function publish(string $topic, $data, ?array $excludeUidList = []): bool;
}