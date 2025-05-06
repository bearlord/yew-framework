<?php

namespace Yew\Plugins\Redis;

use RedisSentinel;

class RedisSentinelFactory
{
    /**
     * @var bool
     */
    protected bool $isOlderThan6 = false;

    public function __construct()
    {
        $this->isOlderThan6 = (bool)version_compare(phpversion('redis'), '6.0.0', '<');
    }

    /**
     * @param array $options
     * @return RedisSentinel
     */
    public function create(array $options = []): RedisSentinel
    {
        if ($this->isOlderThan6) {
            return new RedisSentinel(
                $options['host'],
                (int) $options['port'],
                (float) $options['connectTimeout'],
                $options['persistent'],
                (int) $options['retryInterval'],
                (float) $options['readTimeout'],
            );
        }

        // https://github.com/phpredis/phpredis/blob/develop/sentinel.md#examples-for-version-60-or-later
        return new RedisSentinel($options); /* @phpstan-ignore-line */
    }
}
