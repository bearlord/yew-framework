<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Plugins\Amqp;

use Yew\Core\Exception;
use Yew\Server\Coroutine\Server;
use Yew\Plugins\Amqp\Message\Message;
use PhpAmqpLib\Channel\AMQPChannel;
use function Swlib\Http\str;

class Builder
{
    /**
     * @param Message $message
     * @param AMQPChannel|null $channel
     * @param bool $release
     * @return void
     * @throws \Exception
     */
    public function declare(Message $message, ?AMQPChannel $channel = null, bool $release = false): void
    {
        try {
            if (!$channel) {
                /** @var AmqpConnection $connection */
                $connection = $this->amqp($message->getPoolName());
                $channel = $connection->getChannel();
            }

            $builder = $message->getExchangeBuilder();

            $channel->exchange_declare($builder->getExchange(), $builder->getType(), $builder->isPassive(),
                $builder->isDurable(), $builder->isAutoDelete(), $builder->isInternal(), $builder->isNowait(),
                $builder->getArguments(), $builder->getTicket());
        } catch (Exception $exception) {
            Server::$instance->getLog()->warning((string)$exception);
        }
    }
}