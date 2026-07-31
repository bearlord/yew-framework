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
use Yew\Plugins\Topic\Tools\TopicValidator;
use Yew\Plugins\Uid\GetUid;

class Topic
{
	use GetBoostSend;
	use GetUid;
	use GetLogger;

	/**
	 * In-memory subscription index: topic => [uid => uid].
	 * @var array
	 */
	protected array $subscriptions = [];

    /**
     * Storage driver used to persist and load subscriptions.
     * @var DriverInterface
     */
    private DriverInterface $driver;

    /**
     * Topic constructor.
     *
     * @param DriverInterface $driver Storage driver used to persist/load subscriptions.
     */
	public function __construct(DriverInterface $driver)
	{
        $this->driver = $driver;

		$this->recovery();
	}

    /**
     * Reload all persisted subscriptions from the storage driver into the
     * in-memory index on process startup.
     *
     * Reads subscriptions in batches (paginated via $offset) until the driver
     * returns no more items.
     *
     * @return void
     */
    protected function recovery(): void
    {
        $offset = 0;
        while (true) {
            $batchItems = $this->driver->batchItems(50, $offset);
            if (empty($batchItems)) {
                break;
            }

            foreach ($batchItems as $subscription) {
                $this->indexSubscription($subscription["topic"], $subscription["uid"]);
            }

            // Advance the cursor so the next batch fetches the following rows
            // instead of the same first $limit rows (which would loop forever).
            $offset += count($batchItems);
        }
    }
    
    /**
     * Index a subscription in the in-memory map (topic => [uid => uid]).
     *
     * @param string $topic Subscription topic.
     * @param string $uid Subscriber unique id.
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
	 * Check whether a uid is subscribed to a given topic.
	 *
	 * @param string $topic Subscription topic.
	 * @param string $uid Subscriber unique id.
	 * @return bool True if the uid is subscribed to the topic.
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
	 * Add a subscription for a uid to a topic.
	 *
	 * Validates the topic filter, updates the in-memory index and persists it
	 * through the storage driver.
	 *
	 * @param string $topic Subscription topic.
	 * @param string $uid Subscriber unique id.
	 * @return bool True on success, false if the topic filter is invalid.
	 */
	public function addSubscription(string $topic, string $uid): bool
	{
        $topicValidator = new TopicValidator();
        $validateResult = $topicValidator->validateFilter($topic);
        if (!$validateResult) {
            return false;
        }

		$this->indexSubscription($topic, $uid);

        $this->driver->addSubscription($topic, $uid);

		return true;
	}

	/**
	 * Clear all subscriptions of the uid bound to the given connection fd.
	 *
	 * @param int $fd Connection file descriptor.
	 * @return bool True on success, false if the fd is empty/invalid.
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
	 * Clear (remove) all subscriptions of a given uid across every topic.
	 *
	 * @param string $uid Subscriber unique id.
	 * @return bool True on success, false if the uid is empty.
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
	 * Remove a single uid's subscription from a topic.
	 *
	 * Cleans up the in-memory index (and prunes an empty topic entry) and
	 * removes the record from the storage driver.
	 *
	 * @param string $topic Subscription topic.
	 * @param string $uid Subscriber unique id.
	 * @return bool True on success, false if the uid is empty.
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
	 * Delete an entire topic and all of its subscriber records.
	 *
	 * @param string $topic Subscription topic to delete.
	 * @return bool True on success.
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
	 * Publish data to every uid subscribed to the given topic (and its
	 * matching wildcard patterns).
	 *
	 * @param string $topic Topic to publish to.
	 * @param mixed $data Payload to deliver.
	 * @param array|null $excludeUidList Uids to skip (e.g. the sender).
	 * @return bool True when the publish dispatch finishes.
	 */
	public function publish(string $topic, $data, ?array $excludeUidList = null): bool
	{
		$tree = $this->buildTrees($topic);

		foreach ($tree as $one) {
			if (empty($this->subscriptions[$one])) {
				continue;
			}

			foreach ($this->subscriptions[$one] as $uid) {
				if (!empty($excludeUidList) && in_array($uid, $excludeUidList)) {
					continue;
				}
				$this->publishToUid($uid, $data, $topic);
			}
		}

		return true;
	}

	/**
	 * Build the set of topic patterns (exact + wildcard) that a published
	 * topic should be matched against.
	 *
	 * Generates exact matches, prefix wildcards (#) and single-level
	 * wildcards (+) via a bitmask over the topic segments. System topics
	 * (starting with '$') are protected from wildcard replacement.
	 *
	 * @param string $topic Published topic.
	 * @return array Map of pattern => pattern.
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
	 * Deliver data to a single uid by resolving its connection fd.
	 *
	 * @param string $uid Subscriber unique id.
	 * @param mixed $data Payload to deliver.
	 * @param string $topic Publishing topic (passed to the send helper).
	 * @return bool True if the message was dispatched, false if no fd found.
	 */
	private function publishToUid(string $uid, $data, string $topic): bool
	{
		$fd = $this->getUidFd($uid);

		if (empty($fd)) {
			return false;
		}

		return $this->autoBoostSend($fd, $data, $topic);
	}
}
