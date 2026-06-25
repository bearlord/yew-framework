<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic;

use DI\DependencyException;
use DI\NotFoundException;
use Yew\Core\Exception\Exception;
use Yew\Plugins\Ipc\GetIpc;
use Yew\Plugins\Ipc\IpcException;

trait GetTopic
{
	use GetIpc;

	/**
	 * @var TopicConfig|null
	 */
	protected ?TopicConfig $topicConfig = null;

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
	public function hasTopic(string $topic, string $uid): bool
	{
		if (empty($uid)) {
			return false;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class);
		if (empty($ipcProxy)) {
			return false;
		}

		return $ipcProxy->hasTopic($topic, $uid);
	}

	/**
	 * @param string $topic
	 * @return bool
	 */
	public function deleteTopic(string $topic): bool
	{
		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		if (empty($ipcProxy)) {
			return false;
		}

		return $ipcProxy->deleteTopic($topic);
	}

	/**
	 * @return mixed|TopicConfig|null
	 */
	protected function getTopicConfig()
	{
		if ($this->topicConfig == null) {
			$this->topicConfig = DIGet(TopicConfig::class);
		}
		return $this->topicConfig;
	}

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
	public function addSubscription(string $topic, string $uid): bool
	{
		if (empty($uid)) {
			return false;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		if (empty($ipcProxy)) {
			return false;
		}

		return $ipcProxy->addSubscription($topic, $uid);
	}

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return void
	 */
	public function removeSubscription(string $topic, string $uid)
	{
		if (empty($uid)) {
			return;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->removeSubscription($topic, $uid);
	}

	/**
	 * @param int $fd
	 * @return void
	 */
	public function clearFdSubscription(int $fd)
	{
		if (empty($fd)) {
			return;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->clearFdSubscription($fd);
	}

	/**
	 * @param string $uid
	 * @return void
	 */
	public function clearUidSubbscription(string $uid)
	{
		if (empty($uid)) {
			return;
		}

		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->clearUidSubbscription($uid);
	}

	/**
	 * @param string $topic
	 * @param $data
	 * @param array|null $excludeUidList
	 * @return void
	 */
	public function publish(string $topic, $data, ?array $excludeUidList = null)
	{
		/** @var Topic $ipcProxy */
		$ipcProxy = $this->callProcessName($this->getTopicConfig()->getProcessName(), Topic::class, true);
		$ipcProxy->publish($topic, $data, $excludeUidList);
	}
}
