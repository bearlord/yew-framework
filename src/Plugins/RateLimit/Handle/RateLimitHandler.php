<?php

namespace Yew\Plugins\RateLimit\Handle;

use Yew\Core\Server\Server;
use Yew\Framework\Exception\InvalidArgumentException;
use Yew\Plugins\RateLimit\Storage\StorageInterface;
use Yew\TokenBucket\Rate;
use Yew\TokenBucket\TokenBucket;
use Yew\Yew;

class RateLimitHandler
{

    public const RATE_LIMIT_BUCKETS = 'rateLimit:buckets';

    /**
     * @throws StorageException
     */
    public function build(string $key, int $limit, int $capacity, ?int $timeout = 1): TokenBucket
    {
        $storageConfig = Server::$instance->getConfigContext()->get('yew.rateLimit.storage');

        switch (gettype($storageConfig['class'])) {
            case "string":
                $storage = Yew::$container->get($storageConfig['class'], [$key, $timeout, $storageConfig['options'] ?? []]);
                break;

            default:
                throw new InvalidArgumentException('Invalid configuration of rate limit storage.');
        }


        if (!$storage instanceof StorageInterface) {
            throw new InvalidArgumentException('The storage of rate limit must be an instance of ' . StorageInterface::class);
        }

        $rate = Yew::$container->get(Rate::class, [$limit, Rate::SECOND]);

        $bucket = Yew::$container->get(TokenBucket::class, [$capacity, $rate, $storage]);

        $bucket->bootstrap($capacity);
        return $bucket;
    }

}