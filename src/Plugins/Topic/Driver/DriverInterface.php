<?php

namespace Yew\Plugins\Topic\Driver;

interface DriverInterface
{

    public function addSub(string $topic, string $uid);

    public function removeSub(string $topic, string $uid);

    public function hasTopic(string $topic, string $uid): bool;

    public function delTopic(string $topic);

    public function clearFdSub(int $fd);

    public function clearUidSub(string $uid);
    
    public function pub(string $topic, $data, ?array $excludeUidList = []);


}