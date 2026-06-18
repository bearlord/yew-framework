<?php

namespace Yew\Plugins\Topic\Driver;

use Yew\Plugins\Topic\Driver\Db\DbDriver;
use Yew\Plugins\Topic\Driver\Memory\MemoryDriver;

class DriverStrategy
{

    private DriverInterface $strategy;


    public function __construct(?string $type = null)
    {
        switch ($type) {
            case "db":
                $this->strategy = new DbDriver();
                break;

            case "memory":
            default:
                $this->strategy = new MemoryDriver();
        }
    }


    public function addSubscription(string $topic, string $uid)
    {
        $this->strategy->addSubscription($topic, $uid);
    }

    public function removeSubscription(string $topic, string $uid)
    {
        $this->strategy->removeSubscription($topic, $uid);
    }

    public function hasTopic(string $topic, string $uid): bool
    {
        return $this->strategy->hasTopic($topic, $uid);
    }

    public function delTopic(string $topic)
    {
        $this->strategy->delTopic($topic);
    }

    public function clearFdSubbscription(int $fd)
    {
        $this->strategy->clearFdSubbscription($fd);
    }

    public function clearUidSub(string $uid)
    {
        $this->strategy->clearUidSub($uid);
    }

    public function pub(string $topic, $data, ?array $excludeUidList = [])
    {
        $this->strategy->pub($topic, $data, $excludeUidList);
    }


}