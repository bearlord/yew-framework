<?php

namespace Yew\Plugins\Actor\Log;

use Yew\Yew;

class LogFactory
{
    public static function create(string $name): Logger
    {
        return Yew::createObject([
            "class" => Logger::class,
            "dispatcher" => [
                "class" => Dispatcher::class,
                "targets" => [
                    "class" => FileTarget::class,
                    "logFileName" => $name
                ]
            ]
        ]);
    }
}