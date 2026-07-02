<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic;

use DI\DependencyException;
use DI\NotFoundException;
use Yew\Core\Exception\Exception;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Topic\Storage\DriverInterface;
use Yew\Plugins\Uid\GetUid;

class Topic
{
	use GetBoostSend;
	use GetUid;
	use GetLogger;

	protected array $subscriptions = [];

    /**
     * @var DriverInterface
     */
    private DriverInterface $driver;

    /**
     * @param DriverInterface $driver
     */
	public function __construct(DriverInterface $driver)
	{
        var_dump([
            "driver" => $driver
        ]);

        $this->driver = $driver;

		$this->recovery();
	}

    /**
     * Recovery subscriptions
     * @return void
     */
    protected function recovery(): void
    {
        $allSubscriptions = $this->driver->allSubscriptions();
        if (empty($allSubscriptions)) {
            return;
        }

        foreach ($allSubscriptions as $subscription) {
            $this->indexSubscription($subscription["topic"], $subscription["uid"]);
        }
    }
    
    /**
     * @param string $topic
     * @param string $uid
     * @return void
     */
	private function indexSubscription(string $topic, string $uid): void
	{
		if (empty($uid)) {
			return;
		}

		if (!isset($this->subscriptions[$topic])) {
			$this->subscriptions[$topic] = [];
		}

		$this->subscriptions[$topic][$uid] = $uid;
	}

	/**
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
	public function hasTopic(string $topic, string $uid): bool
	{
		$subs = !empty($this->subscriptions[$topic]) ? $this->subscriptions[$topic] : null;
		if ($subs == null) {
			return false;
		}

		return isset($subs[$uid]);
	}

	/**
	 * Add subscription
	 *
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
	public function addSubscription(string $topic, string $uid): bool
	{

        var_dump([
            "topic" => $topic,
            "uid" => $uid
        ]);

        try {
		    Utility::checkTopicFilter($topic);
        } catch (\Exception $exception) {
            var_dump([
                "exception" => $exception->getMessage(),
                "code" => $exception->getCode(),
                "trace" => $exception->getTraceAsString(),
                "file" => $exception->getFile(),
                "line" => $exception->getLine()
            ]);
            return false;
        }

		$this->indexSubscription($topic, $uid);


        $this->driver->addSubscription($topic, $uid);

		return true;
	}

	/**
	 * Clear fd subscription
	 *
	 * @param int $fd
	 */
	public function clearFdSubscription(int $fd): bool
	{
		if (empty($fd)) {
			return false;
		}

		$uid = $this->getFdUid($fd);

		return $this->clearUidSubscription($uid);
	}

	/**
	 * Clear uid subscription
	 *
	 * @param string $uid
	 * @return bool
	 */
	public function clearUidSubscription(string $uid): bool
	{
		if (empty($uid)) {
			return false;
		}

		foreach ($this->subscriptions as $topic => $sub) {
			$this->removeSubscription($topic, $uid);
		}

		return true;
	}

	/**
	 * Remove subscription
	 * @param string $topic
	 * @param string $uid
	 * @return bool
	 */
	public function removeSubscription(string $topic, string $uid): bool
	{
		if (empty($uid)) {
			return false;
		}
		if (isset($this->subscriptions[$topic])) {
			unset($this->subscriptions[$topic][$uid]);

			if (empty($this->subscriptions[$topic])) {
				unset($this->subscriptions[$topic]);
			}
		}

        $this->driver->removeSubscription($topic, $uid);

		return true;
	}

	/**
	 * Delete subscription
	 *
	 * @param string $topic
	 * @return bool
	 */
	public function deleteTopic(string $topic): bool
	{
		$uidItems = !empty($this->subscriptions[$topic]) ? $this->subscriptions[$topic] : [];

		unset($this->subscriptions[$topic]);

		foreach ($uidItems as $uid) {
            $this->driver->removeSubscription($topic, $uid);
		}

		return true;
	}

	/**
	 * Publish subscription
	 *
	 * @param string $topic
	 * @param $data
	 * @param array|null $excludeUidList
	 * @return bool
	 */
	public function publish(string $topic, $data, ?array $excludeUidList = null): bool
	{
		$tree = $this->buildTrees($topic);

		foreach ($tree as $one) {
			if (empty($this->subscriptions[$one])) {
				continue;
			}

			foreach ($this->subscriptions[$one] as $uid) {
				if (in_array($uid, $excludeUidList)) {
					continue;
				}
				$this->publishToUid($uid, $data, $topic);
			}
		}

		return true;
	}

	/**
	 * Build a subscription tree, allowing only 5 layers
	 *
	 * @param string $topic
	 * @return array
	 */
	private function buildTrees(string $topic): array
	{
		$segments = explode('/', $topic);
		$levelCount = count($segments);
		$result = [];
		$isSys = $topic[0] === '$';

		if (!$isSys) {
			$result['#'] = '#';
		}

		for ($level = 1; $level <= $levelCount; $level++) {
			$levelSegments = array_slice($segments, 0, $level);
			$isComplete = $level === $levelCount;
			$exactTopic = implode('/', $levelSegments);

			// Exact match and prefix wildcard
			$result[$exactTopic . '/#'] = $exactTopic . '/#';
			if ($isComplete) {
				$result[$exactTopic] = $exactTopic;
			}

			// Generate + wildcard combinations via bitmask
			$firstReplaceableIdx = $isSys ? 1 : 0;
			$variantCount = 1 << $level;

			for ($mask = 1; $mask < $variantCount; $mask++) {
				// Skip masks that would replace the system prefix '$'
				if ($mask & ((1 << $firstReplaceableIdx) - 1)) {
					continue;
				}

				$variant = $levelSegments;
				for ($pos = $firstReplaceableIdx; $pos < $level; $pos++) {
					if ($mask & (1 << $pos)) {
						$variant[$pos] = '+';
					}
				}

				$variantTopic = implode('/', $variant);
				$result[$variantTopic . '/#'] = $variantTopic . '/#';
				if ($isComplete) {
					$result[$variantTopic] = $variantTopic;
				}
			}
		}

		return $result;
	}

	/**
	 * Publish subscription to uid
	 *
	 * @param string $uid
	 * @param $data
	 * @param string $topic
	 */
	private function publishToUid(string $uid, $data, string $topic): bool
	{
		$fd = $this->getUidFd($uid);
		if (empty($uid)) {
			return false;
		}

		return $this->autoBoostSend($fd, $data, $topic);
	}
}
