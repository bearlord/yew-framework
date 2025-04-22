<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Port;

use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Core\Server\Beans\WebSocketFrame;

/**
 * Interface IServerPort
 * @package Yew\Core\Server\Port
 */
interface IServerPort
{
    /**
     * @param int $fd
     * @param int $reactorId
     * @return mixed
     */
    public function onTcpConnect(int $fd, int $reactorId);


    /**
     * @param int $fd
     * @param int $reactorId
     * @return mixed
     */
    public function onTcpClose(int $fd, int $reactorId);


    /**
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     * @return mixed
     */
    public function onTcpReceive(int $fd, int $reactorId, string $data);


    /**
     * @param string $data
     * @param array $client_info
     * @return mixed
     */
    public function onUdpPacket(string $data, array $client_info);


    /**
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function onHttpRequest(Request $request, Response $response);


    /**
     * @param WebSocketFrame $frame
     * @return mixed
     */
    public function onWsMessage(WebSocketFrame $frame);


    /**
     * @param Request $request
     * @return mixed
     */
    public function onWsOpen(Request $request);


    /**
     * @param Request $request
     * @return bool
     */
    public function onWsPassCustomHandshake(Request $request): bool;
}