<?php

namespace Yew\Plugins\Mqtt\Topic;

/**
 * Delivery boundary for pushing a published message to a single subscriber.
 *
 * Decouples MqttTopic (subscription matching) from HOW a clientId is reached.
 * The default LocalDeliveryGateway resolves the client's fd on the local
 * connection process and sends directly; a future actor-based gateway can
 * instead address the connection actor that owns the clientId (possibly on
 * another node) via a message, without touching MqttTopic or business code.
 */
interface DeliveryGatewayInterface
{
    /**
     * Deliver data to a single subscriber by clientId.
     *
     * @param string $clientId Subscriber client_id.
     * @param mixed  $data     Payload to deliver.
     * @param string $topic    Topic the message was published to.
     * @return bool True if delivery was attempted/handled, false if the
     *              clientId could not be resolved (e.g. not connected here).
     */
    public function deliver(string $clientId, $data, string $topic): bool;
}
