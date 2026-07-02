<?php

namespace Yew\Plugins\Topic\Storage\Memory;

use Yew\Plugins\Topic\Storage\DriverInterface;

class RedisDriver implements DriverInterface
{
	public function init()
	{
		echo "init";
	}

    /**
     * Add a subscription for a uid to a topic
     *
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function addSubscription(string $topic, string $uid): bool
    {
        return true;
    }

    /**
     * Remove a subscription for a uid from a topic
     *
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function removeSubscription(string $topic, string $uid): bool
    {
        return true;
    }

    /**
     * Check if a uid has subscribed to a topic
     *
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function hasTopic(string $topic, string $uid): bool
    {
        return true;
    }

    /**
     * Delete all subscriptions for a topic
     *
     * @param string $topic
     * @return bool
     */
    public function deleteTopic(string $topic): bool
    {
        return true;
    }

    /**
     * @return array|null
     */
    public function allSubscriptions(): ?array
    {
        return null;
    }


    /**
     * Build key
     * @param string $topic
     * @param string $uid
     * @return string
     */
    protected function buildKey(string $topic, string $uid)
    {
        return sprintf("%s::%s", $topic, $uid);
    }
}