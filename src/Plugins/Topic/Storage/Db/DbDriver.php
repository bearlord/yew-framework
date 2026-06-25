<?php

namespace Yew\Plugins\Topic\Storage\Db;

use Yew\Plugins\Topic\Storage\DriverInterface;

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