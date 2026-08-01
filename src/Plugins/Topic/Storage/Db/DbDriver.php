<?php

namespace Yew\Plugins\Topic\Storage\Db;

use Carbon\Carbon;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Db\Connection;
use Yew\Framework\Db\Query;
use Yew\Framework\Db\Schema;
use Yew\Plugins\Topic\Storage\DriverInterface;
use Yew\Yew;

/**
 * Database-backed topic subscription driver for persistent storage.
 *
 * Persists topic-subscriber mappings to a relational database table,
 * ensuring data survives server restarts. Supports auto-creation of
 * the subscriptions table if it does not exist.
 */
class DbDriver implements DriverInterface
{

    /**
     * @var string The storage driver type identifier
     */
    protected string $type = "db";

    /**
     * @var string The database connection key from configuration
     */
    protected string $dbKey = "default";

    /**
     * @var Connection|null The active database connection instance
     */
    protected ?Connection $db = null;

    /**
     * @var string The name of the table storing topic subscriptions
     */
    protected string $tableName = "topic_subscriptions";

    /**
     * Initialize the DbDriver with configuration values and set up the database connection
     *
     * @param array $config Configuration array with optional 'dbKey' and 'table' keys
     */
    public function __construct(array $config)
    {
        if (!empty($config["dbKey"])) {
            $this->dbKey = $config["dbKey"];
        }

        if (!empty($config["table"])) {
            $this->tableName = $config["table"];
        }

        $this->init();
    }

    /**
     * Initialize the driver by setting up the database connection and verifying table existence
     *
     * @return void
     */
    public function init()
    {
        $this->setDb();

        if (!$this->checkTableExists()) {
            $this->createTable();
        }
    }

    /**
     * Get the storage driver type identifier
     *
     * @return string The driver type
     */
    public function getType(): string
    {
        return $this->type;
    }


    /**
     * Set up the database connection and resolve the table prefix from configuration
     *
     * @return void
     */
    protected function setDb()
    {
        if (empty($this->dbKey)) {
            $this->db = Yew::$app->getDb();
        }
    }

    /**
     * Get the database connection instance by key
     *
     * @return Connection The database connection instance
     */
    protected function getDb()
    {
        if (empty($this->dbKey)) {
            return Yew::$app->getDb();
        }
        return Yew::$app->getDb($this->dbKey);
    }

    /**
     * Check whether the topic subscriptions table exists in the database
     *
     * @return bool True if the table exists, false otherwise
     */
    protected function checkTableExists(): bool
    {
        $tableName = $this->tableName;

        $tableSchema = $this->getDb()->getSchema()->getTableSchema("{{%$tableName}}");
        if (empty($tableSchema)) {
            return false;
        }

        return true;
    }

    /**
     * Create the topic subscriptions table with required columns and indexes
     *
     * @return void
     */
    protected function createTable()
    {
        $tableName = $this->tableName;

        $this->getDb()->createCommand()->createTable("{{%$tableName}}", [
            "id" => Schema::TYPE_PK,
            "uid" => Schema::TYPE_INTEGER . " NOT NULL",
            "topic" => Schema::TYPE_STRING . "(240) NOT NULL",
            "created_at" => Schema::TYPE_INTEGER,
            "updated_at" => Schema::TYPE_INTEGER
        ])->execute();

        $this->getDb()->createCommand()->createIndex(
            "idx_" . $tableName . "_uid",
            "{{%$tableName}}",
            "uid"
        )->execute();

        $this->getDb()->createCommand()->createIndex(
            "idx_" . $tableName . "_topic",
            "{{%$tableName}}",
            "topic"
        )->execute();
    }

    /**
     * Add a subscription for a uid to a topic
     *
     * @param string $topic The topic pattern to subscribe to
     * @param string $uid The unique identifier of the subscriber
     * @return bool True on success
     */
    public function addSubscription(string $topic, string $uid): bool
    {
        $tableName = $this->tableName;

        $currentTimestamp = (Carbon::now())->getTimestamp();

        $exists = (new Query())->from("{{%$tableName}}")->where([
            "uid" => $uid,
            "topic" => $topic
        ])->exists($this->getDb());

        if ($exists) {
            return true;
        }

        $this->getDb()->createCommand()->insert("{{%$tableName}}", [
            "uid" => $uid,
            "topic" => $topic,
            "created_at" => $currentTimestamp,
            "updated_at" => $currentTimestamp
        ])->execute();

        return true;
    }

    /**
     * Remove a subscription for a uid from a topic
     *
     * @param string $topic The topic pattern to unsubscribe from
     * @param string $uid The unique identifier of the subscriber
     * @return bool True on success
     */
    public function removeSubscription(string $topic, string $uid): bool
    {
        $tableName = $this->tableName;
        $this->getDb()->createCommand()->delete("{{%$tableName}}", [
            "uid" => $uid,
            "topic" => $topic
        ])->execute();

        return true;
    }

    /**
     * Delete all subscriptions for a topic
     *
     * @param string $topic The topic pattern whose subscribers should be removed
     * @return bool True on success
     */
    public function deleteTopic(string $topic): bool
    {
        $tableName = $this->tableName;
        
        $this->getDb()->createCommand()->delete("{{%$tableName}}", [
            "topic" => $topic
        ])->execute();

        return true;
    }

    /**
     * Retrieve all items from the database
     *
     * @return array An array of all items stored in the database
     */
    public function allItems(): ?array
    {
        $tableName = $this->tableName;

        $items = (new Query())->from("{{%$tableName}}")->select([
            "uid", "topic"
        ])->orderBy([
            "id" => SORT_ASC
        ])->all($this->getDb());

        if (empty($items)) {
            return null;
        }

        return $items;
    }
    
    /**
     * Retrieve a batch of items from the database
     *
     * @param int $limit The maximum number of items to retrieve
     * @param int $offset The number of items to skip (for pagination)
     * @return array|null An array of items, or null if no items found
     */
    public function batchItems(int $limit = 50, int $offset = 0): ?array
    {
        $tableName = $this->tableName;

        $items = (new Query())->from("{{%$tableName}}")->select([
            "uid", "topic"
        ])->orderBy([
            "id" => SORT_ASC
        ])->limit($limit)->offset($offset)->all($this->getDb());

        if (empty($items)) {
            return null;
        }

        return $items;
    }

    /**
     * Retrieve all distinct topics that have subscriptions
     *
     * @return array|null List of distinct topics, or null if none exist
     */
    public function allSubscriptions(): ?array
    {
        $tableName = $this->tableName;

        $items = (new Query())->from("{{%$tableName}}")->select([
            "topic"
        ])->groupBy([
            "topic"
        ])->all($this->getDb());

        if (empty($items)) {
            return null;
        }

        return $items;
    }

    /**
     * Retrieve all distinct subscriber uids across all topics
     *
     * @return array|null List of distinct subscriber uids, or null if none exist
     */
    public function allSubscribers(): ?array
    {
        $tableName = $this->tableName;

        $items = (new Query())->from("{{%$tableName}}")->select([
            "uid"
        ])->groupBy([
            "uid"
        ])->all($this->getDb());

        if (empty($items)) {
            return null;
        }

        return $items;
    }

    /**
     * Get all subscribers for a given topic
     *
     * @param string $topic The topic pattern to look up
     * @return array|null List of subscriber uids subscribed to the topic, or null if none found
     */
    public function getSubscribers(string $topic): ?array
    {
        $tableName = $this->tableName;

        $items = (new Query())->from("{{%$tableName}}")->select([
            "uid"
        ])->where([
            "topic" => $topic
        ])->all($this->getDb());

        if (empty($items)) {
            return null;
        }
        return $items;
    }

    /**
     * Get all topic subscriptions for a given uid
     *
     * @param int $uid The unique identifier of the subscriber
     * @return array|null List of topic patterns the uid is subscribed to, or null if none found
     */
    public function getSubscriptions(int $uid): ?array
    {
        $tableName = $this->tableName;

        $items = (new Query())->from("{{%$tableName}}")->select([
            "topic"
        ])->where([
            "uid" => $uid
        ])->all($this->getDb());

        if (empty($items)) {
            return null;
        }

        return $items;
    }



}