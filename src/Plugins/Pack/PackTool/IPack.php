<?php
/**
 * ESD framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Pack\PackTool;

use Yew\Core\Server\Config\PortConfig;
use Yew\Plugins\Pack\ClientData;

/**
 * Interface IPack
 * @package Yew\Plugins\Pack\PackTool
 */
interface IPack
{
    /**
     * @param mixed $buffer
     * @return mixed
     */
    public function encode($buffer);

    /**
     * @param mixed $buffer
     * @return mixed
     */
    public function decode($buffer);

    /**
     * @param mixed $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return mixed
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null);

    /**
     * @param int $fd
     * @param mixed $data
     * @param PortConfig $portConfig
     * @return ClientData|null
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData;

    /**
     * @param PortConfig $portConfig
     * @return mixed
     */
    public static function changePortConfig(PortConfig $portConfig);
}