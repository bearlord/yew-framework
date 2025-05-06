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

class NonJsonPack implements IPack
{
    use GetLogger;

    /**
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null)
    {
        try {
            $res = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!$res) {
                $res = "";
            }
        } catch (\Exception $th) {
            $res = "";
        }

        return $res;
    }

    /**
     * Packet unpack
     *
     * @param int $fd
     * @param string $data
     * @param PortConfig $portConfig
     * @return ClientData
     * @throws \Yew\Core\Plugins\Config\ConfigException
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        $value = json_decode($data, true);
        if (empty($value)) {
            $this->warn(Yii::t('esd', 'Packet unpack failed'));
            return null;
        }
        $clientData = new ClientData($fd, $portConfig->getBaseType(), $value['p'], $value);
        return $clientData;
    }

    /**
     * Packet encode
     * @param string $buffer
     */
    public function encode($buffer)
    {
        return;
    }

    /**
     * Packet decode
     *
     * @param string $buffer
     */
    public function decode($buffer)
    {
        return;
    }

    /**
     * Change port config
     *
     * @param PortConfig $portConfig
     * @throws \Exception
     */
    public static function changePortConfig(PortConfig $portConfig)
    {
        if ($portConfig->isOpenWebsocketProtocol()) {
            return true;
        } else {
            Server::$instance->getLog()->warning("NonJsonPack is used but WebSocket protocol is not enabled ,we are automatically turn on WebsocketProtocol for you.");
            $portConfig->setOpenWebsocketProtocol(true);
        }
    }
}