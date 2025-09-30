<?php

namespace Yew\Plugins\Topic\Driver\Memory;

use Yew\Plugins\Topic\Driver\DriverInterface;

class MemoryDriver implements DriverInterface
{
    public function addSub(string $topic, string $uid)
    {
        // TODO: Implement addSub() method.
    }

    public function removeSub(string $topic, string $uid)
    {
        // TODO: Implement removeSub() method.
    }

    public function hasTopic(string $topic, string $uid): bool
    {
        // TODO: Implement hasTopic() method.
    }

    public function delTopic(string $topic)
    {
        // TODO: Implement delTopic() method.
    }

    public function clearFdSub(int $fd)
    {
        // TODO: Implement clearFdSub() method.
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