<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

use Yew\Core\Channel\Channel;

class IpcManager
{
    private static int $token = 0;

    /**
     * @var Channel[];
     */
    private static array $channelMap = [];


    public static function getToken(): int
    {
        return self::$token++;
    }

    /**
     * @param $token
     * @return Channel
     * @throws \Exception
     */
    public static function getChannel($token): Channel
    {
        self::$channelMap[$token] = DIGet(Channel::class);
        return self::$channelMap[$token];
    }

    /**
     * @param $token
     * @param $data
     */
    public static function callChannel($token, $data)
    {
        $channel = self::$channelMap[$token] ?? null;
        if ($channel != null) {
            $channel->push($data);
            unset(self::$channelMap[$token]);
        }
    }
}