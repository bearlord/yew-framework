<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Uid;

use DI\DependencyException;
use DI\NotFoundException;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Coroutine\Server\Server;

trait GetUid
{
    /**
     * @var UidBean|null
     */
    protected ?UidBean $uidBean = null;

    /**
     * @return UidBean
     */
    protected function getUidBean(): UidBean
    {
        if ($this->uidBean == null) {
            $this->uidBean = Server::$instance->getContainer()->get(UidBean::class);
        }
        return $this->uidBean;
    }

    /**
     * @param string $uid
     */
    public function kickUid(string $uid)
    {
        $this->getUidBean()->kickUid($uid);
    }

    /**
     * @param int $fd
     * @param string $uid
     * @param bool $autoKick
     */
    public function bindUid(int $fd, string $uid, ?bool $autoKick = true)
    {
        $this->getUidBean()->bindUid($fd, $uid, $autoKick);
    }

    /**
     * @param int $fd
     */
    public function unBindUid(int $fd)
    {
        $this->getUidBean()->unBindUid($fd);
    }

    /**
     * @param string $uid
     * @return mixed
     */
    public function getUidFd(string $uid)
    {
        return $this->getUidBean()->getUidFd($uid);
    }

    /**
     * @param int $fd
     * @return mixed
     */
    public function getFdUid(int $fd)
    {
        return $this->getUidBean()->getFdUid($fd);
    }

    /**
     * @param $uid
     * @return bool
     */
    public function isOnline($uid): bool
    {
        return $this->getUidBean()->isOnline($uid);
    }

    /**
     * @return int
     */
    public function getUidCount(): int
    {
        return $this->getUidBean()->getUidCount();
    }

    /**
     * @return array
     */
    public function getAllUid(): array
    {
        return $this->getUidBean()->getAllUid();
    }

    /**
     * @return array
     */
    public function getAllFd(): array
    {
        return $this->getUidBean()->getAllFd();
    }

    /**
     * @return Table
     */
    public function getUidFdTable(): Table
    {
        return $this->getUidBean()->getUidFdTable();
    }

    /**
     * @return Table
     */
    public function getFdUidTable(): Table
    {
        return $this->getUidBean()->getFdUidTable();
    }
}