<?php

namespace Yew\Plugins\Topic\Driver;

interface DriverInterface
{

    public function addSubscription(string $topic, string $uid);

    public function removeSubscription(string $topic, string $uid);

    public function hasTopic(string $topic, string $uid): bool;

    public function delTopic(string $topic);

    public function clearFdSubbscription(int $fd);

    public function clearUidSub(string $uid);
    
    public function pub(string $topic, $data, ?array $excludeUidList = []);


}