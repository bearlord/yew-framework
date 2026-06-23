<?php

namespace Yew\Plugins\Topic\Driver;

interface DriverInterface
{

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return mixed
	 */
    public function addSubscription(string $topic, string $uid);

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return mixed
	 */
    public function removeSubscription(string $topic, string $uid);

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
    public function hasTopic(string $topic, string $uid): bool;

	/**
	 * @param string $topic
	 * @return mixed
	 */
    public function deleteTopic(string $topic);

	/**
	 * @param int $fd
	 * @return mixed
	 */
    public function clearFdSubbscription(int $fd);

	/**
	 * @param string $uid
	 * @return mixed
	 */
    public function clearUidSubbscription(string $uid);

	/**
	 * @param string $topic
	 * @param $data
	 * @param array|null $excludeUidList
	 * @return mixed
	 */
    public function publish(string $topic, $data, ?array $excludeUidList = []);


}