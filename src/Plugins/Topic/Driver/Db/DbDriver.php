<?php

namespace Yew\Plugins\Topic\Driver\Db;

use Yew\Plugins\Topic\Driver\DriverInterface;

class DbDriver implements DriverInterface
{
    public function addSubscription(string $topic, string $uid)
    {
        // TODO: Implement addSubscription() method.
    }

    public function removeSubscription(string $topic, string $uid)
    {
        // TODO: Implement removeSubscription() method.
    }

    public function hasTopic(string $topic, string $uid): bool
    {
        // TODO: Implement hasTopic() method.
    }

    public function delTopic(string $topic)
    {
        // TODO: Implement delTopic() method.
    }

    public function clearFdSubbscription(int $fd)
    {
        // TODO: Implement clearFdSubbscription() method.
    }

    public function clearUidSub(string $uid)
    {
        // TODO: Implement clearUidSub() method.
    }

    public function pub(string $topic, $data, ?array $excludeUidList = [])
    {
        // TODO: Implement pub() method.
    }


}