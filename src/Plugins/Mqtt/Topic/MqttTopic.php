<?php
/**
 * Yew framework - MQTT topic routing (hosted in the mqtt-connection process)
 *
 * Subscription matching & message dispatch for the MQTT broker, hosted inside
 * the mqtt-connection helper process. This class is composed by
 * Yew\Plugins\Mqtt\Connection\MqttConnection so that subscription state and
 * publish dispatch live in the same process that already owns the
 * clientId <-> fd routing table.
 *
 * Subscribers are keyed by client_id (one clientId <-> one fd), so a
 * published message resolves the target fd directly via
 * getClientSession($clientId, 'fd') instead of a separate client_id lookup.
 */

namespace Yew\Plugins\Mqtt\Topic;

use Yew\Plugins\Mqtt\Connection\MqttConnection;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Mqtt\Topic\Storage\DriverInterface;
use Yew\Mqtt\Tools\TopicValidator;

class MqttTopic
{
    use GetBoostSend;

    /**
     * Owner connection process, used to resolve clientId -> fd and to send.
     * @var MqttConnection|null
     */
    private ?MqttConnection $connection = null;

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
     * Wire this component to its owning MqttConnection (for fd resolution / send).
     *
     * @param MqttConnection $connection
     * @return void
     */
    public function setConnection(MqttConnection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Initialize the topic index. Call once inside the mqtt-connection process
     * after wiring the connection (see MqttConnectionPlugin), or lazily via
     * MqttConnection::getMqttTopic().
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
     * Deliver data to a single clientId by resolving its connection fd and
     * sending through the pack aspect.
     *
     * @param string $clientId
     * @param mixed  $data
     * @param string $topic
     * @return bool
     */
    private function publishToClientId(string $clientId, $data, string $topic): bool
    {
        if ($this->connection === null) {
            return false;
        }
        $fd = $this->connection->getClientSession($clientId, 'fd');
        if (empty($fd)) {
            return false;
        }

        return $this->autoBoostSend((int)$fd, $data, $topic);
    }
}
