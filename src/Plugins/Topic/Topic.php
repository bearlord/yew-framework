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
use Yew\Plugins\Uid\GetUid;

class Topic
{
    use GetBoostSend;
    use GetUid;
    use GetLogger;

    protected array $subscriptionItems = [];

    /**
     * @var Table
     */
    private Table $topicTable;

    /**
     * Topic constructor.
     * @param Table $topicTable
     */
    public function __construct(Table $topicTable)
    {
        //Read the table first, because the process may restart
        $this->topicTable = $topicTable;

        foreach ($this->topicTable as $value) {
            $this->addSubscriptionFormTable($value["topic"], $value["uid"]);
        }
    }

    /**
     * @param string $topic
     * @param string $uid
     */
    private function addSubscriptionFormTable(string $topic, string $uid)
    {
        if (empty($uid)) {
            return;
        }

        if (!isset($this->subscriptionItems[$topic])) {
            $this->subscriptionItems[$topic] = [];
        }

        $this->subscriptionItems[$topic][$uid] = $uid;
    }

    /**
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function hasTopic(string $topic, string $uid): bool
    {
        $subs = !empty($this->subscriptionItems[$topic]) ? $this->subscriptionItems[$topic] : null;
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
     * @throws BadUTF8
     * @throws Exception
     */
    public function addSubscription(string $topic, string $uid)
    {
        Utility::checkTopicFilter($topic);

        $this->addSubscriptionFormTable($topic, $uid);
        $this->topicTable->set($topic . $uid, [
			"topic" => $topic,
	        "uid" => $uid
        ]);
    }

    /**
     * Clear fd"s subscription
     *
     * @param int $fd
     * @throws \Exception
     */
    public function clearFdSubscription(int $fd)
    {
        if (empty($fd)) {
            return;
        }

        $uid = $this->getFdUid($fd);
        $this->clearUidSubbscription($uid);
    }

    /**
     * Clear uid"s subscription
     *
     * @param string $uid
     * @throws \Exception
     */
    public function clearUidSubscription(string $uid)
    {
        if (empty($uid)) {
            return;
        }

        foreach ($this->subscriptionItems as $topic => $sub) {
            $this->removeSubscription($topic, $uid);
        }
    }

    /**
     * Remove subscription
     * @param string $topic
     * @param string $uid
     * @throws \Exception
     */
    public function removeSubscription(string $topic, string $uid)
    {
        if (empty($uid)) {
            return;
        }
        if (isset($this->subscriptionItems[$topic])) {
            unset($this->subscriptionItems[$topic][$uid]);

            if (empty($this->subscriptionItems[$topic])) {
                unset($this->subscriptionItems[$topic]);
            }
        }

        $this->topicTable->del($topic . $uid);
        $this->debug("$uid Remove Sub $topic");
    }

    /**
     * Delete subscription
     *
     * @param string $topic
     */
    public function delTopic(string $topic)
    {
        $uidItems = !empty($this->subscriptionItems[$topic]) ? $this->subscriptionItems[$topic] : [];

        unset($this->subscriptionItems[$topic]);

        foreach ($uidItems as $uid) {
            $this->topicTable->del($topic . $uid);
        }
    }

    /**
     * Publish subscription
     *
     * @param string $topic
     * @param $data
     * @param array|null $excludeUidList
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function pub(string $topic, $data, ?array $excludeUidList = null)
    {
        $tree = $this->buildTrees($topic);

        foreach ($tree as $one) {
	        if (empty($this->subscriptionItems[$one])) {
				continue;
	        }

	        foreach ($this->subscriptionItems[$one] as $uid) {
		        if (in_array($uid, $excludeUidList)) {
			        continue;
		        }
		        $this->pubToUid($uid, $data, $topic);
	        }
        }
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
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function pubToUid(string $uid, $data, string $topic)
    {
        $fd = $this->getUidFd($uid);
        if (empty($uid)) {
            return;
        }
        $this->autoBoostSend($fd, $data, $topic);
    }
}
