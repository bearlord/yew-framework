<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Beans;

use Swoole\WebSocket\Frame;

class WebSocketFrame
{
    private int $fd;

    private string $data = "";

    private int $opcode;

    private bool $finish;

    private $swooleFrame;

    /**
     * WebSocketFrame constructor.
     * @param Frame $frame
     */
    public function __construct(Frame $frame)
    {
        $this->swooleFrame = $frame;
        $this->fd = $frame->fd;
        $this->opcode = $frame->opcode;
        $this->data = $frame->data;
        $this->finish = $frame->finish;
    }

    /**
     * @return int
     */
    public function getFd(): int
    {
        return $this->fd;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getOpcode(): int
    {
        return $this->opcode;
    }

    /**
     * @return bool
     */
    public function getFinish(): bool
    {
        return $this->finish;
    }

    /**
     * @return Frame
     */
    public function getSwooleFrame(): Frame
    {
        return $this->swooleFrame;
    }

    /**
     * @param int $fd
     */
    public function setFd(int $fd): void
    {
        $this->fd = $fd;
    }

    /**
     * @param string $data
     */
    public function setData(string $data): void
    {
        $this->data = $data;
    }

    /**
     * @param int $opcode
     */
    public function setOpcode(int $opcode): void
    {
        $this->opcode = $opcode;
    }

    /**
     * @param bool $finish
     */
    public function setFinish(bool $finish): void
    {
        $this->finish = $finish;
    }
}