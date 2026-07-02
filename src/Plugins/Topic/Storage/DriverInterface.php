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
     * Delete all subscriptions for a topic
     *
     * @param string $topic
     * @return bool
     */
    public function deleteTopic(string $topic): bool;
}