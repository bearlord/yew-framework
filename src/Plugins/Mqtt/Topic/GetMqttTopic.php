<?php
/**
 * Yew framework - MQTT topic routing (client side)
 *
 * Trait consumed by business/worker code to manage subscriptions and publish
 * through the mqtt-connection helper process. The topic index lives on the
 * Yew\Plugins\Mqtt\Topic\MqttTopic bean (registered in that process); the
 * clientId <-> fd routing table lives on MqttConnection.
 *
 * The process-name resolver is declared privately (instead of reusing
 * GetMqttConnection::getMqttConnectionConfig()) so a class may use both traits
 * without a property/method collision.
 */

namespace Yew\Plugins\Mqtt\Topic;

use Yew\Plugins\Ipc\GetIpc;
use Yew\Plugins\Mqtt\Connection\MqttConnectionConfig;
use Yew\Plugins\Mqtt\Topic\MqttTopic;

trait GetMqttTopic
{
    use GetIpc;

    /**
     * Resolve the mqtt-connection process name from the DI container.
     *
     * @return string
     */
    private function mqttConnectionProcessName(): string
    {
        return DIGet(MqttConnectionConfig::class)->getProcessName();
    }

    /**
     * Whether a clientId is subscribed to a given topic.
     *
     * @param string $topic
     * @param string $clientId
     * @return bool
     */
    public function hasTopic(string $topic, string $clientId): bool
    {
        if ($clientId === '') {
            return false;
        }
        /** @var MqttTopic $ipcProxy */
        $ipcProxy = $this->callProcessName($this->mqttConnectionProcessName(), MqttTopic::class);
        if (empty($ipcProxy)) {
            return false;
        }
        return $ipcProxy->hasTopic($topic, $clientId);
    }

    /**
     * Resolve every subscriber clientId for a topic (wildcards included).
     *
     * @param string $topic
     * @return array|null
     */
    public function getSubscribers(string $topic): ?array
    {
        /** @var MqttTopic $ipcProxy */
        $ipcProxy = $this->callProcessName($this->mqttConnectionProcessName(), MqttTopic::class, false);
        if (empty($ipcProxy)) {
            return null;
        }
        return $ipcProxy->getSubscribers($topic);
    }

    /**
     * Add a subscription for a clientId to a topic on the mqtt-connection process.
     *
     * @param string $topic
     * @param string $clientId
     * @param int    $qos
     * @return bool
     */
    public function addSubscription(string $topic, string $clientId, int $qos = 0): bool
    {
        if ($clientId === '') {
            return false;
        }
        /** @var MqttTopic $ipcProxy */
        $ipcProxy = $this->callProcessName($this->mqttConnectionProcessName(), MqttTopic::class, true);
        if (empty($ipcProxy)) {
            return false;
        }
        // Oneway IPC returns null (fire-and-forget); never propagate it as bool.
        $ipcProxy->addSubscription($topic, $clientId, $qos);
        return true;
    }

    /**
     * Remove a single clientId's subscription from a topic.
     *
     * @param string $topic
     * @param string $clientId
     * @return bool
     */
    public function removeSubscription(string $topic, string $clientId): bool
    {
        if ($clientId === '') {
            return false;
        }
        /** @var MqttTopic $ipcProxy */
        $ipcProxy = $this->callProcessName($this->mqttConnectionProcessName(), MqttTopic::class, true);
        if (empty($ipcProxy)) {
            return false;
        }
        $ipcProxy->removeSubscription($topic, $clientId);
        return true;
    }

    /**
     * Clear all subscriptions of a clientId across every topic.
     *
     * @param string $clientId
     * @return bool
     */
    public function clearClientSubscription(string $clientId): bool
    {
        if ($clientId === '') {
            return false;
        }
        /** @var MqttTopic $ipcProxy */
        $ipcProxy = $this->callProcessName($this->mqttConnectionProcessName(), MqttTopic::class, true);
        if (empty($ipcProxy)) {
            return false;
        }
        $ipcProxy->clearClientSubscription($clientId);
        return true;
    }

    /**
     * Publish data to every subscriber of a topic.
     *
     * @param string $topic
     * @param mixed  $data
     * @param array|null $excludeClientIdList
     * @return bool
     */
    public function publish(string $topic, $data, ?array $excludeClientIdList = null): bool
    {
        /** @var MqttTopic $ipcProxy */
        $ipcProxy = $this->callProcessName($this->mqttConnectionProcessName(), MqttTopic::class, true);
        if (empty($ipcProxy)) {
            return false;
        }
        $ipcProxy->publish($topic, $data, $excludeClientIdList);
        return true;
    }
}
