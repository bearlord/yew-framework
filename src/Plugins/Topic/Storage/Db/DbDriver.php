<?php

namespace Yew\Plugins\Topic\Storage\Db;

use Yew\Plugins\Topic\Storage\DriverInterface;

class DbDriver implements DriverInterface
{
	public function __construct()
	{
		
	}

	public function init()
	{

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
     * All subscriptions
     * @return array|null
     */
    public function allSubscriptions(): ?array
    {
        return null;
    }
}