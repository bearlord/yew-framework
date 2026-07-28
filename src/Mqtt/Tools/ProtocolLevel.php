<?php

namespace Yew\Mqtt\Tools;

class ProtocolLevel
{
    const PROTOCOL_LEVEL_V3_1 = 3;

    const PROTOCOL_LEVEL_V3_1_1 = 4;

    const PROTOCOL_LEVEL_V5 = 5;

    /**
     * @return string[]
     */
    public static function protocolLevels(): array
    {
        return [
            self::PROTOCOL_LEVEL_V3_1 => 'v3.1',
            self::PROTOCOL_LEVEL_V3_1_1 => 'v3.1.1',
            self::PROTOCOL_LEVEL_V5 => '5.0'
        ];
    }

    /**
     * @param int $level
     * @return string
     */
    public static function getProtocolLevelName(int $level): string
    {
        $all = self::protocolLevels();
        if (isset($all[$level])) {
            return $all[$level];
        }
        return 'unknown';
    }
}