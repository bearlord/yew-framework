<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Pack;

use Yew\Coroutine\Server\Server;
use Yew\Plugins\Pack\Aspect\PackAspect;

/**
 * Trait GetBoostSend
 * @package Yew\Plugins\Pack
 */
trait GetBoostSend
{
    protected $packAspect;

    /**
     * @return PackAspect
     */
    protected function getPackAspect(): PackAspect
    {
        if ($this->packAspect == null) {
            $packPlugin = Server::$instance->getPlugManager()->getPlug(PackPlugin::class);
            if ($packPlugin instanceof PackPlugin) {
                $this->packAspect = $packPlugin->getPackAspect();
            }
        }
        return $this->packAspect;
    }

    /**
     * Enhanced send, which can be transcoded and sent according to different protocols
     *
     * @param $fd
     * @param $data
     * @param $topic
     * @return bool
     */
    public function autoBoostSend($fd, $data, $topic = null): bool
    {
        return $this->getPackAspect()->autoBoostSend($fd, $data, $topic);
    }
}