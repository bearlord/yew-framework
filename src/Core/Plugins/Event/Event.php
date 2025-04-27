<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Event;

class Event
{
    /**
     * Event type
     * @var string
     */
    private string $type;

    /**
     * Event data
     * @var mixed
     */
    private $data;

    /**
     * Source info
     * @var array
     */
    private array $sourceInfo = [];

    /**
     * Destination info
     * @var array
     */
    private array $dstInfo = [];

    /**
     * Progress
     * @var string
     */
    private string $progress = "";

    /**
     * Event constructor.
     *
     * @param string $type
     * @param $data
     */
    public function __construct(string $type, $data)
    {
        $this->type = $type;
        $this->data = $data;
        $this->setDstInfo(TypeEventDispatcher::type, [$type]);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return int|null
     */
    public function getProcessId(): ?int
    {
        return $this->getSourceInfo(ProcessEventDispatcher::type);
    }

    /**
     * @param $type
     * @return mixed
     */
    public function getSourceInfo($type)
    {
        return $this->sourceInfo[$type] ?? null;
    }

    /**
     * @param $type
     * @param $data
     */
    public function setSourceInfo($type, $data): void
    {
        $this->sourceInfo[$type] = $data;
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getDstInfo(string $type)
    {
        return $this->dstInfo[$type] ?? null;
    }

    /**
     * Set destination info
     *
     * @param string $type
     * @param $data
     */
    public function setDstInfo(string $type, $data): void
    {
        $this->dstInfo[$type] = $data;
    }

    /**
     * Get progress
     *
     * @return string
     */
    public function getProgress(): ?string
    {
        return $this->progress;
    }

    /**
     * Set progress
     *
     * @param string $progress
     */
    public function setProgress(string $progress): void
    {
        $this->progress = $progress;
    }
}