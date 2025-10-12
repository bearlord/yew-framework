<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Log;

use Yew\Yew;

class LogFactory
{
    public static function create(string $name): Logger
    {
        return Yew::createObject([
            "class" => Logger::class,
            "flushInterval" => 1,
            "dispatcher" => Yew::createObject([
                "class" => Dispatcher::class,
                "targets" => [
                    Yew::createObject([
                        "class" => FileTarget::class,
                        "logFileName" => $name,
                        "exportInterval" => 2,
                    ])
                ]
            ])
        ]);
    }
}