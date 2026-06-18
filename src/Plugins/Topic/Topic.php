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
            $this->addSubFormTable($value["topic"], $value["uid"]);
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
    public function addSub(string $topic, string $uid)
    {
        Utility::checkTopicFilter($topic);

        $this->addSubFormTable($topic, $uid);
        $this->topicTable->set($topic . $uid, ["topic" => $topic, "uid" => $uid]);
    }

    /**
     * Clear fd"s subscription
     *
     * @param int $fd
     * @throws \Exception
     */
    public function clearFdSub(int $fd)
    {
        if (empty($fd)) {
            return;
        }

        $uid = $this->getFdUid($fd);
        $this->clearUidSub($uid);
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
            $this->removeSub($topic, $uid);
        }
    }

    /**
     * Remove subscription
     * @param string $topic
     * @param string $uid
     * @throws \Exception
     */
    public function removeSub(string $topic, string $uid)
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
        $isSys = false;
        if ($topic[0] == "$") {
            $isSys = true;
        }
        $p = explode("/", $topic);
        $countPlies = count($p);
        $result = [];
        if (!$isSys) {
            $result["#"] = "#";
        }
        for ($j = 0; $j < $countPlies; $j++) {
            $a = array_slice($p, 0, $j + 1);
            $arr = [$a];
            $count_a = count($a);
            $value = implode("/", $a);
            $result[$value . "/#"] = $value . "/#";
            $complete = false;
            if ($count_a == $countPlies) {
                $complete = true;
                $result[$value] = $value;
            }
            for ($i = 0; $i < $count_a; $i++) {
                $temp = [];
                foreach ($arr as $one) {
                    $this->helpReplacePlus($one, $temp, $result, $complete, $isSys);
                }
                $arr = $temp;
            }
        }
        return $result;
    }

    /**
     * @param $arr
     * @param $temp
     * @param $result
     * @param $complete
     * @param $isSYS
     */
    private function helpReplacePlus($arr, &$temp, &$result, $complete, $isSYS)
    {
        $count = count($arr);
        $m = 0;
        if ($isSYS) $m = 1;
        for ($i = $m; $i < $count; $i++) {
            $new = $arr;
            if ($new[$i] == "+") continue;
            $new[$i] = "+";
            $temp[] = $new;
            $value = implode("/", $new);
            $result[$value . "/#"] = $value . "/#";
            if ($complete) {
                $result[$value] = $value;
            }
        }
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
