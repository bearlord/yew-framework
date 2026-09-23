<?php
/**
 * Yew framework - MQTT topic routing
 *
 * Subscription matching & message dispatch for the MQTT broker. This class
 * holds the in-memory subscription index (Trie) and is intended to become the
 * state of a subscription actor. It is deliberately free of any connection/fd
 * knowledge: once a topic is matched to subscriber clientIds, delivery is
 * delegated to an injected DeliveryGateway (local fd send by default, or a
 * cross-node actor message in a distributed deployment).
 *
 * Subscribers are keyed by client_id. Persistence (if a storage driver is
 * bound) is used only to recover the index on startup, not on the hot path.
 */

namespace Yew\Plugins\Mqtt\Topic;

use Yew\Plugins\Mqtt\Topic\Storage\DriverInterface;
use Yew\Mqtt\Tools\TopicValidator;

class MqttTopic
{
    /**
     * Delivery boundary: how a matched subscriber is reached (local fd now,
     * actor message later). Injected so MqttTopic stays free of connection/fd
     * assumptions and is ready to become an actor's state.
     * @var DeliveryGatewayInterface
     */
    private DeliveryGatewayInterface $deliveryGateway;

    /**
     * In-memory subscription index: clientId => [topic => qos].
     * @var array<string, array<string, int>>
     */
    protected array $topicSubscriptions = [];

    /**
     * Subscription matching index (Trie), maintained incrementally.
     * @var Trie|null
     */
    protected ?Trie $topicTrie = null;

    /**
     * Optional persistence driver (reuses the Topic plugin's storage).
     * @var DriverInterface|null
     */
    protected ?DriverInterface $topicDriver = null;

    /**
     * Recovery guard so persisted subscriptions are loaded exactly once.
     * @var bool
     */
    private bool $topicRecovered = false;

    /**
     * Wire this component to a delivery gateway. The gateway encapsulates how a
     * matched subscriber clientId is actually reached (local fd send by default,
     * or cross-node actor message in a distributed deployment).
     *
     * @param DeliveryGatewayInterface $deliveryGateway
     */
    public function __construct(DeliveryGatewayInterface $deliveryGateway)
    {
        $this->deliveryGateway = $deliveryGateway;
        $this->init();
    }

    /**
     * Initialize the topic index. Invoked automatically by the constructor,
     * so it is always built once the MqttTopic is instantiated (see
     * MqttConnectionPlugin).
     *
     * @return void
     */
    public function init(): void
    {
        if ($this->topicTrie === null) {
            $this->topicTrie = new Trie();
        }
        $this->ensureRecovered();
    }

    /**
     * Lazily load persisted subscriptions from the storage driver (if any)
     * into the in-memory index on first use.
     *
     * @return void
     */
    private function ensureRecovered(): void
    {
        if ($this->topicRecovered) {
            return;
        }

        $driver = $this->getTopicDriver();
        if ($driver === null) {
            // Storage driver not bound yet (e.g. plugin load order); retry on the
            // next subscription / publish call instead of skipping recovery forever.
            return;
        }

        $this->topicRecovered = true;

        $offset = 0;
        while (true) {
            $batchItems = $driver->batchItems(50, $offset);
            if (empty($batchItems)) {
                break;
            }
            foreach ($batchItems as $subscription) {
                $topic = $subscription['topic'] ?? null;
                $clientId = $subscription['client_id'] ?? null;
                if ($topic !== null && $clientId !== null && $clientId !== '') {
                    $this->indexSubscription((string)$topic, (string)$clientId, 0);
                }
            }
            $offset += count($batchItems);
        }
    }

    /**
     * Resolve the persistence driver from the DI container; null if none bound.
     *
     * @return DriverInterface|null
     */
    private function getTopicDriver(): ?DriverInterface
    {
        if ($this->topicDriver === null) {
            try {
                $this->topicDriver = DIGet(DriverInterface::class);
            } catch (\Throwable $e) {
                $this->topicDriver = null;
            }
        }
        return $this->topicDriver;
    }

    /**
     * Index a subscription in the in-memory map and Trie.
     *
     * @param string $topic    Subscription filter.
     * @param string $clientId Subscriber clientId.
     * @param int    $qos      Requested QoS.
     * @return void
     */
    private function indexSubscription(string $topic, string $clientId, int $qos = 0): void
    {
        if ($clientId === '') {
            return;
        }
        if ($this->topicTrie === null) {
            $this->topicTrie = new Trie();
        }
        if (!isset($this->topicSubscriptions[$clientId])) {
            $this->topicSubscriptions[$clientId] = [];
        }
        $this->topicSubscriptions[$clientId][$topic] = $qos;
        $this->topicTrie->insert($topic, $clientId);
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
        return isset($this->topicSubscriptions[$clientId][$topic]);
    }

    /**
     * Resolve every subscriber clientId for a published topic (wildcards included).
     *
     * @param string $topic
     * @return array<int, string>
     */
    public function getSubscribers(string $topic): array
    {
        $this->ensureRecovered();
        if ($this->topicTrie === null) {
            return [];
        }
        return array_values($this->topicTrie->match($topic));
    }

    /**
     * Add a subscription for a clientId to a topic.
     *
     * @param string $topic
     * @param string $clientId
     * @param int    $qos
     * @return bool True on success, false if the filter is invalid / clientId empty.
     */
    public function addSubscription(string $topic, string $clientId, int $qos = 0): bool
    {
        if ($clientId === '') {
            return false;
        }
        $this->ensureRecovered();

        $validator = new TopicValidator();
        if (!$validator->validateFilter($topic)) {
            return false;
        }

        $this->indexSubscription($topic, $clientId, $qos);

        $driver = $this->getTopicDriver();
        if ($driver !== null) {
            $driver->addSubscription($topic, $clientId);
        }

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
        if ($clientId === '' || !isset($this->topicSubscriptions[$clientId][$topic])) {
            return false;
        }
        $this->ensureRecovered();

        unset($this->topicSubscriptions[$clientId][$topic]);
        if (empty($this->topicSubscriptions[$clientId])) {
            unset($this->topicSubscriptions[$clientId]);
        }

        if ($this->topicTrie !== null) {
            $this->topicTrie->remove($topic, $clientId);
        }

        $driver = $this->getTopicDriver();
        if ($driver !== null) {
            $driver->removeSubscription($topic, $clientId);
        }

        return true;
    }

    /**
     * Clear (remove) all subscriptions of a clientId across every topic.
     *
     * @param string $clientId
     * @return bool
     */
    public function clearClientSubscription(string $clientId): bool
    {
        if ($clientId === '' || !isset($this->topicSubscriptions[$clientId])) {
            return false;
        }
        $this->ensureRecovered();

        foreach (array_keys($this->topicSubscriptions[$clientId]) as $topic) {
            if ($this->topicTrie !== null) {
                $this->topicTrie->remove($topic, $clientId);
            }
            $driver = $this->getTopicDriver();
            if ($driver !== null) {
                $driver->removeSubscription($topic, $clientId);
            }
        }
        unset($this->topicSubscriptions[$clientId]);

        return true;
    }

    /**
     * Publish data to every subscriber of a topic (wildcards included).
     *
     * @param string $topic
     * @param mixed  $data
     * @param array|null $excludeClientIdList clientIds to skip (e.g. the sender).
     * @return bool
     */
    public function publish(string $topic, $data, ?array $excludeClientIdList = null): bool
    {
        $this->ensureRecovered();
        if ($this->topicTrie === null) {
            return false;
        }

        foreach ($this->topicTrie->match($topic) as $clientId) {
            if (!empty($excludeClientIdList) && in_array($clientId, $excludeClientIdList, true)) {
                continue;
            }
            $this->publishToClientId($clientId, $data, $topic);
        }

        return true;
    }

    /**
     * Deliver data to a single clientId via the injected DeliveryGateway.
     * The gateway decides how the clientId is reached (local fd or, in a
     * distributed deployment, a message to the owning connection actor).
     *
     * @param string $clientId
     * @param mixed  $data
     * @param string $topic
     * @return bool
     */
    private function publishToClientId(string $clientId, $data, string $topic): bool
    {
        return $this->deliveryGateway->deliver($clientId, $data, $topic);
    }
}
