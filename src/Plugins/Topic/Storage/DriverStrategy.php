<?php

namespace Yew\Plugins\Topic\Storage;

use Yew\Core\Memory\CrossProcess\Table;
use Yew\Plugins\Topic\Storage\Db\DbDriver;
use Yew\Plugins\Topic\Storage\Memory\MemoryDriver;

class DriverStrategy
{

	private DriverInterface $strategy;

    private Table $topicTable;

	/**
	 * @param array $config
	 */
	public function __construct(array $config, Table $topicTable)
	{
        $this->topicTable = $topicTable;

		$type = $config["type"] ?? "memory";
		switch ($type) {
			case "db":
				$this->strategy = new DbDriver();
				break;

			case "memory":
			default:
				$this->strategy = new MemoryDriver();
		}

	}

	/**
	 * @return void
	 */
	public function init()
	{
		$this->strategy->init();
	}

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
	public function addSubscription(string $topic, string $uid): bool
	{
		return $this->strategy->addSubscription($topic, $uid);
	}

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
	public function removeSubscription(string $topic, string $uid): bool
	{
		return $this->strategy->removeSubscription($topic, $uid);
	}

	/**
	 * @param string $topic
	 * @return bool
	 */
	public function deleteTopic(string $topic): bool
	{
		$this->strategy->deleteTopic($topic);
	}


}