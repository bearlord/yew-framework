<?php

namespace Yew\Plugins\Topic\Storage\Memory;

use Yew\Core\DI\DI;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Plugins\Topic\Storage\DriverInterface;

class MemoryDriver implements DriverInterface
{
    private Table $topicTable;
    
	public function init()
	{
        $this->topicTable = DI::getInstance()->get("topicTable");
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
        $key = sprintf("%s::%s", $topic, $uid);

        $this->topicTable->set($key, [
            "topic" => $topic,
            "uid" => $uid
        ]);

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
        $key = sprintf("%s::%s", $topic, $uid);
        $this->topicTable->delete($key);

        return true;
    }


    /**
     * Delete all subscriptions for a topic
     *
     * @param string $topic
     * @return bool
     */
    public function deleteTopic(string $topic, string $uid): bool
    {
        $key = sprintf("%s::%s", $topic, $uid);
        $this->topicTable->delete($key);

        return true;
    }
}