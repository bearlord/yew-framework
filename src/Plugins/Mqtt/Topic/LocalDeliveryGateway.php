<?php

namespace Yew\Plugins\Mqtt\Topic;

use Yew\Plugins\Mqtt\Connection\MqttConnection;
use Yew\Plugins\Pack\GetBoostSend;

/**
 * Default DeliveryGatewayInterface implementation: resolves the subscriber's fd
 * on the local connection process and sends through the pack aspect
 * (autoBoostSend).
 *
 * Used as long as the subscription actor and connection actor share the same
 * process. Swap this for an actor-message-based gateway when distributing
 * across nodes, without changing MqttTopic or any business code.
 */
class LocalDeliveryGateway implements DeliveryGatewayInterface
{
    use GetBoostSend;

    /**
     * Owner connection process, used to resolve clientId -> fd.
     * @var MqttConnection
     */
    private MqttConnection $connection;

    /**
     * @param MqttConnection $connection
     */
    public function __construct(MqttConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $clientId
     * @param mixed  $data
     * @param string $topic
     * @return bool
     */
    public function deliver(string $clientId, $data, string $topic): bool
    {
        $fd = $this->connection->getClientSession($clientId, 'fd');
        if (empty($fd)) {
            return false;
        }

        return $this->autoBoostSend((int)$fd, $data, $topic);
    }
}
