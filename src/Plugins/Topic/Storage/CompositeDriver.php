<?php

namespace Yew\Plugins\Topic\Storage;

/**
 * Composite storage driver that delegates operations to multiple underlying drivers.
 *
 * Write operations (add/remove/delete) are broadcast to all drivers.
 * Read operations are served by the persistent (first) driver for performance.
 * Typical usage: memory as persistent for fast reads, db as secondary for persistence.
 */
class CompositeDriver implements DriverInterface
{
    /** @var DriverInterface[] */
    private array $drivers = [];

    /**
     * @var string The storage driver type identifier
     */
    protected string $type = 'composite';

    /**
     * @param DriverInterface[] $drivers Ordered list of drivers; first one is persistent for reads
     */
    public function __construct(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * Initialize all underlying drivers
     *
     * @return void
     */
    public function init()
    {
        foreach ($this->drivers as $driver) {
            $driver->init();
        }
    }

    /**
     * Get the storage driver type identifier
     *
     * @return string The storage driver type identifier
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Add a subscription across all drivers
     *
     * @param string $topic The topic pattern to subscribe to
     * @param string $uid The subscriber unique identifier
     * @return bool True if all drivers succeeded
     */
    public function addSubscription(string $topic, string $uid): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->addSubscription($topic, $uid)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Remove a subscription across all drivers
     *
     * @param string $topic The topic pattern to unsubscribe from
     * @param string $uid The subscriber unique identifier
     * @return bool True if all drivers succeeded
     */
    public function removeSubscription(string $topic, string $uid): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->removeSubscription($topic, $uid)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete a topic across all drivers
     *
     * @param string $topic The topic pattern to delete
     * @return bool True if all drivers succeeded
     */
    public function deleteTopic(string $topic): bool
    {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->deleteTopic($topic)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Get all items from the persistent driver
     *
     * @return array|null All items from the persistent driver
     */
    public function allItems(): ?array
    {
        return $this->persistent()->allItems();
    }

    /**
     * Get a batch of items from the persistent driver
     *
     * @param int $limit The maximum number of items to retrieve
     * @return array|null A batch of items from the persistent driver
     */
    public function batchItems(int $limit = 50): ?array
    {
        return $this->persistent()->batchItems($limit);
    }

    /**
     * Get all subscriptions from the persistent driver
     *
     * @return array|null All subscriptions from the persistent driver
     */
    public function allSubscriptions(): ?array
    {
        return $this->persistent()->allSubscriptions();
    }

    /**
     * Get all subscribers from the persistent driver
     *
     * @return array|null All subscribers from the persistent driver
     */
    public function allSubscribers(): ?array
    {
        return $this->persistent()->allSubscribers();
    }

    /**
     * Get subscribers for a topic from the persistent driver
     *
     * @param string $topic The topic pattern to look up
     * @return array|null List of subscriber uids
     */
    public function getSubscribers(string $topic): ?array
    {
        return $this->persistent()->getSubscribers($topic);
    }

    /**
     * Get subscriptions for a uid from the persistent driver
     *
     * @param int $uid The subscriber unique identifier
     * @return array|null List of topic patterns
     */
    public function getSubscriptions(int $uid): ?array
    {
        return $this->persistent()->getSubscriptions($uid);
    }
    

    /**
     * Get the persistent (first) driver for read operations
     *
     * @return DriverInterface
     */
    private function persistent(): DriverInterface
    {
        foreach ($this->drivers as $driver) {
            if ($driver->getType() === "db") {
                return $driver;
            }
        }
    }
}
