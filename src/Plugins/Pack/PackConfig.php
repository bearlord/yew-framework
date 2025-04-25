<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack;

use Yew\Core\Server\Config\PortConfig;
use Yew\Plugins\Pack\PackTool\LenJsonPack;
use Yew\Plugins\Pack\PackTool\NonJsonPack;

/**
 * Class PackConfig
 * @package Yew\Plugins\Pack
 */
class PackConfig extends PortConfig
{
    /**
     * @var string
     */
    protected $packTool;

    /**
     * @throws \Yew\Core\Plugins\Config\ConfigException
     * @throws \ReflectionException
     */
    public function merge()
    {
        if ($this->isOpenWebsocketProtocol() && $this->packTool == null) {
            $this->packTool = NonJsonPack::class;
        } else if (!$this->isOpenHttpProtocol() && $this->packTool == null) {
            $this->packTool = LenJsonPack::class;
        }
        parent::merge();
    }

    /**
     * @return string
     */
    public function getPackTool(): ?string
    {
        return $this->packTool;
    }

    /**
     * @param string $packTool
     */
    public function setPackTool(string $packTool): void
    {
        $this->packTool = $packTool;
    }
}