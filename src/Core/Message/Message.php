<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Message;

use Yew\Core\Server\Server;

class Message
{
    /**
     * Type
     * @var string
     */
    private string $type;

    /**
     * Event content
     * @var mixed
     */
    private $data;

    /**
     * @var int
     */
    private int $fromProcessId;

    /**
     * Message constructor.
     * @param string $type
     * @param $data
     */
    public function __construct(string $type, $data)
    {
        $this->type = $type;
        $this->data = $data;
        $this->fromProcessId = Server::$instance->getProcessManager()->getCurrentProcessId();
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Get data
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set data
     *
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * To string
     *
     * @return string
     */
    public function toString(): string
    {
        $jsonData = json_encode($this->data);
        return "{\"type\":\"$this->type\",\"data\":\"$jsonData\"}";
    }

    /**
     * Get from process id
     *
     * @return int
     */
    public function getFromProcessId(): int
    {
        return $this->fromProcessId;
    }

    /**
     * Set from process id
     *
     * @param int $fromProcessId
     */
    public function setFromProcessId(int $fromProcessId): void
    {
        $this->fromProcessId = $fromProcessId;
    }
}