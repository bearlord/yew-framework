<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic;

use DI\DependencyException;
use DI\NotFoundException;
use Ds\Set;
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

    protected array $subArr = [];

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
            $this->addSubFormTable($value['topic'], $value['uid']);
        }
    }

    /**
     * @param string $topic
     * @param string $uid
     */
    private function addSubFormTable(string $topic, string $uid)
    {
        if (empty($uid)) {
            return;
        }

        if (!isset($this->subArr[$topic])) {
            $this->subArr[$topic] = new Set();
        }

        $this->subArr[$topic]->add($uid);
    }

    /**
     * @param string $topic
     * @param string $uid
     * @return bool
     */
    public function hasTopic(string $topic, string $uid): bool
    {
        $set = !empty($this->subArr[$topic]) ? $this->subArr[$topic] : null;
        if ($set == null) {
            return false;
        }

        return $set->contains($uid);
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
     * Clear fd's subscription
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
     * Clear uid's subscription
     *
     * @param string $uid
     * @throws \Exception
     */
    public function clearUidSub(string $uid)
    {
        if (empty($uid)) {
            return;
        }

        foreach ($this->subArr as $topic => $sub) {
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
        if (isset($this->subArr[$topic])) {
            $this->subArr[$topic]->remove($uid);
            if ($this->subArr[$topic]->count() == 0) {
                unset($this->subArr[$topic]);
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
        $uidArr = !empty($this->subArr[$topic]) ? $this->subArr[$topic] : [];
        unset($this->subArr[$topic]);

        foreach ($uidArr as $uid) {
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
    public function pub(string $topic, $data, ?array $excludeUidList = [])
    {
        $tree = $this->buildTrees($topic);

        foreach ($tree as $one) {
            if (isset($this->subArr[$one])) {
                foreach ($this->subArr[$one] as $uid) {
                    if (!in_array($uid, $excludeUidList)) {
                        $this->pubToUid($uid, $data, $topic);
                    }
                }
            }
        }
    }

    /**
     * Build a subscription tree, allowing only 5 layers
     *
     * @param string $topic
     * @return Set
     */
    private function buildTrees(string $topic): Set
    {
        $isSYS = false;
        if ($topic[0] == "$") {
            $isSYS = true;
        }
        $p = explode("/", $topic);
        $countPlies = count($p);
        $result = new Set();
        if (!$isSYS) {
            $result->add("#");
        }
        for ($j = 0; $j < $countPlies; $j++) {
            $a = array_slice($p, 0, $j + 1);
            $arr = [$a];
            $count_a = count($a);
            $value = implode('/', $a);
            $result->add($value . "/#");
            $complete = false;
            if ($count_a == $countPlies) {
                $complete = true;
                $result->add($value);
            }
            for ($i = 0; $i < $count_a; $i++) {
                $temp = [];
                foreach ($arr as $one) {
                    $this->helpReplacePlus($one, $temp, $result, $complete, $isSYS);
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
            if ($new[$i] == '+') continue;
            $new[$i] = '+';
            $temp[] = $new;
            $value = implode('/', $new);
            $result->add($value . "/#");
            if ($complete) {
                $result->add($value);
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
