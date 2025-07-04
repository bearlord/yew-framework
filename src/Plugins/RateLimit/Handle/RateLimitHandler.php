<?php

namespace Yew\Plugins\RateLimit\Handle;

use Yew\Yew;

class RateLimitHandler
{

    public const RATE_LIMIT_BUCKETS = 'rateLimit:buckets';

    /**
     * @throws StorageException
     */
    public function build(string $key, int $limit, int $capacity, int $timeout): TokenBucket
    {
        $config = $this->container->get(ConfigInterface::class);

        $storageClass = $config->get('rate_limit.storage.class', RedisStorage::class);

        switch (gettype($storageClass)) {
            case "string":
                $storage = Yew::createObject($storageClass, [
                    'key' => $key,
                    'timeout' => $timeout,
                    'options' => $config->get('rate_limit.storage.options', [])
                ]);
                break;

            case "object":
                $storage = $storageClass;
                break;

            default:
                throw new InvalidArgumentException('Invalid configuration of rate limit storage.');
        }


        if (!$storage instanceof StorageInterface) {
            throw new InvalidArgumentException('The storage of rate limit must be an instance of ' . StorageInterface::class);
        }

        $rate = make(Rate::class, ['tokens' => $limit, 'unit' => Rate::SECOND]);
        $bucket = make(TokenBucket::class, ['capacity' => $capacity, 'rate' => $rate, 'storage' => $storage]);
        $bucket->bootstrap($capacity);
        return $bucket;
    }

}