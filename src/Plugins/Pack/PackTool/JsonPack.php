<?php

namespace Yew\Plugins\Pack\PackTool;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Config\PortConfig;
use Yew\Plugins\Pack\ClientData;

class JsonPack implements IPack
{
    use GetLogger;

    /**
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param int $fd
     * @param $data
     * @param PortConfig $portConfig
     * @return ClientData|null
     * @throws \Yew\Core\Exception\ConfigException
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        $value = json_decode($data, true);
        if (empty($value)) {
            $this->warn('json unPack 失败');
            return null;
        }
        if (empty($value['action'])) {
            $this->warn('Parameter error');
            return null;
        }

        return new ClientData($fd, $portConfig->getBaseType(), $value['action'], $value);
    }

    /**
     * @param $buffer
     * @return mixed
     */
    public function encode($buffer)
    {
        return $buffer;
    }

    /**
     * @param $buffer
     * @return mixed
     */
    public function decode($buffer)
    {
        return $buffer;
    }

    /**
     * @param PortConfig $portConfig
     * @return void
     */
    public static function changePortConfig(PortConfig $portConfig)
    {
    }
}