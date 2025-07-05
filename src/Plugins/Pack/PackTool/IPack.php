<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack\PackTool;

use Yew\Core\Server\Config\PortConfig;
use Yew\Plugins\Pack\ClientData;

interface IPack
{
    /**
     * @param mixed $buffer
     * @return mixed
     */
    public function encode($buffer): string;

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
    public function pack($data, PortConfig $portConfig, ?string $topic = null): string;

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