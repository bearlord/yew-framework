<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack\PackTool;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Config\PortConfig;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Pack\ClientData;

/**
 * Class EofJsonPack
 * @package Yew\Plugins\Pack\PackTool
 */
class EofJsonPack extends AbstractPack
{
    use GetLogger;

    /**
     * Packet encode
     *
     * @param $buffer
     * @return string
     */
    public function encode($buffer): string
    {
        return $buffer . $this->portConfig->getPackageEof();
    }

    /**
     * Packet decode
     *
     * @param $buffer
     * @return string
     */
    public function decode($buffer): string
    {
        return str_replace($this->portConfig->getPackageEof(), '', $buffer);
    }

    /**
     * Data pack
     *
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null): string
    {
        $this->portConfig = $portConfig;

        return $this->encode(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Packet unpack
     *
     * @param int $fd
     * @param string $data
     * @param PortConfig $portConfig
     * @return mixed
     * @throws \Yew\Core\Plugins\Config\ConfigException
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        $this->portConfig = $portConfig;
        $value = json_decode($this->decode($data), true);

        if (empty($value)) {
            $this->warn('Packet unpack failed');
            return null;
        }

        return new ClientData($fd, $portConfig->getBaseType(), $value['p'], $value);
    }

    /**
     * Change port config
     *
     * @param PortConfig $portConfig
     * @return true|void
     * @throws \Exception
     */
    public static function changePortConfig(PortConfig $portConfig)
    {
        if ($portConfig->isOpenEofCheck() || $portConfig->isOpenEofSplit()) {
            return true;
        } else {
            Server::$instance->getLog()->warning('Packet used EofJsonPack but EOF protocol is not enabled, Enable open_eof_split automatically');
            $portConfig->setOpenEofSplit(true);
        }
    }
}