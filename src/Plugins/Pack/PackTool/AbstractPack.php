<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack\PackTool;

use Yew\Core\Server\Config\PortConfig;
use Yew\Plugins\Pack\PackException;
use Yew\Yii\Yii;

/**
 * Class AbstractPack
 * @package Yew\Plugins\Pack\PackTool
 */
abstract class AbstractPack implements IPack
{
    /**
     * @var PortConfig
     */
    protected $portConfig;

    /**
     * Get length
     *
     * c: signed, 1 bytes
     * C: unsigned, 1 bytes
     * s: signed, Host byte order, 2 bytes
     * S: unsigned, Host byte order, 2 bytes
     * n: unsigned, network byte order, 2 bytes
     * N: unsigned, network byte order, 4 bytes
     * l: signed, Host byte order, 4 bytes
     * L: unsigned, Host byte order, 4 bytes
     * v: unsigned, little-endian、2 bytes
     * V: unsigned, little-endian、4 bytes
     *
     * @param string $type
     * @return int
     * @throws PackException
     */
    protected function getLength(string $type)
    {
        switch ($type) {
            case "C":
            case "c":
                return 1;
            case "S":
            case "n":
            case "v":
            case "s":
                return 2;
            case "l":
            case "L":
            case "V":
            case "N":
                return 4;
            default:
                throw new PackException('Wrong Packet type');
        }
    }
}