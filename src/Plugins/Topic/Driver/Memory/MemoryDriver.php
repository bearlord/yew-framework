<?php

namespace Yew\Plugins\Topic\Driver\Memory;

use Yew\Plugins\Topic\Driver\DriverInterface;

class MemoryDriver implements DriverInterface
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

    public function deleteTopic(string $topic)
    {
        // TODO: Implement deleteTopic() method.
    }

    public function clearFdSubbscription(int $fd)
    {
        // TODO: Implement clearFdSubbscription() method.
    }

    public function clearUidSubbscription(string $uid)
    {
        // TODO: Implement clearUidSubbscription() method.
    }

    public function publish(string $topic, $data, ?array $excludeUidList = [])
    {
        // TODO: Implement publish() method.
    }


}