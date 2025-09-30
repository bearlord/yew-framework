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


    public function addSub(string $topic, string $uid)
    {
        $this->strategy->addSub($topic, $uid);
    }

    public function removeSub(string $topic, string $uid)
    {
        $this->strategy->removeSub($topic, $uid);
    }

    public function hasTopic(string $topic, string $uid): bool
    {
        return $this->strategy->hasTopic($topic, $uid);
    }

    public function delTopic(string $topic)
    {
        $this->strategy->delTopic($topic);
    }

    public function clearFdSub(int $fd)
    {
        $this->strategy->clearFdSub($fd);
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