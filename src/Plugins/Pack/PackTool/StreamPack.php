<?php

/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>
 * @author Bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack\PackTool;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Config\PortConfig;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Pack\ClientData;
use Yew\Framework\Helpers\Json;
use Yew\Yew;

class StreamPack extends AbstractPack
{
    use GetLogger;

    /**
     * Packet encode
     *
     * @param $buffer
     * @return string
     */
    public function encode($buffer)
    {
        return $buffer . $this->portConfig->getPackageEof();
    }

    /**
     * Packet decode
     *
     * @param string $buffer
     * @return string
     */
    public function decode($buffer)
    {
        $data = str_replace($this->portConfig->getPackageEof(), '', $buffer);
        return $data;
    }

    /**
     * Data pack
     *
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null)
    {
        $this->portConfig = $portConfig;
        return $this->encode($data);
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
        //Value can be empty
        $value = $this->decode($data);
        $clientData = new ClientData($fd, $portConfig->getBaseType(), 'onReceive', $value);
        return $clientData;
    }

    /**
     * Change port config
     *
     * @param PortConfig $portConfig
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