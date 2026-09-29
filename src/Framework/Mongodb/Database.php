<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yew\Framework\Mongodb;

use Yew\Framework\Base\BaseObject;
use Yew\Yew;

/**
 * Database represents the MongoDB database information.
 *
 * @property-read File\Collection $fileCollection Mongo GridFS collection.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Database extends BaseObject
{
    /**
     * @var Connection MongoDB connection.
     */
    public Connection $connection;
    /**
     * @var string name of this database.
     */
    public string $name;

    /**
     * @var Collection[] list of collections.
     */
    private array $_collections = [];
    /**
     * @var File\Collection[] list of GridFS collections.
     */
    private array $_fileCollections = [];


    /**
     * Returns the Mongo collection with the given name.
     * @param string $name collection name
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
     * @return Collection Mongo collection instance.
     */
    public function getCollection(string $name, bool $refresh = false): Collection
    {
        if ($refresh || !array_key_exists($name, $this->_collections)) {
            $this->_collections[$name] = $this->selectCollection($name);
        }

        return $this->_collections[$name];
    }

    /**
     * Returns Mongo GridFS collection with given prefix.
     * @param string $prefix collection prefix.
     * @param bool $refresh whether to reload the collection instance even if it is found in the cache.
     * @return File\Collection Mongo GridFS collection.
     */
    public function getFileCollection(string $prefix = 'fs', bool $refresh = false): \Yew\Framework\Mongodb\File\Collection
    {
        if ($refresh || !array_key_exists($prefix, $this->_fileCollections)) {
            $this->_fileCollections[$prefix] = $this->selectFileCollection($prefix);
        }

        return $this->_fileCollections[$prefix];
    }

    /**
     * Selects collection with given name.
     * @param string $name collection name.
     * @return Collection collection instance.
     */
    protected function selectCollection(string $name): Collection
    {
        return Yew::createObject([
            'class' => 'Yew\Framework\Mongodb\Collection',
            'database' => $this,
            'name' => $name,
        ]);
    }

    /**
     * Selects GridFS collection with given prefix.
     * @param string $prefix file collection prefix.
     * @return File\Collection file collection instance.
     */
    protected function selectFileCollection(string $prefix): \Yew\Framework\Mongodb\File\Collection
    {
        return Yew::createObject([
            'class' => 'Yew\Framework\Mongodb\File\Collection',
            'database' => $this,
            'prefix' => $prefix,
        ]);
    }

    /**
     * Creates MongoDB command associated with this database.
     * @param array $document command document contents.
     * @return Command command instance.
     * @since 2.1
     */
    public function createCommand(array $document = []): Command
    {
        return $this->connection->createCommand($document, $this->name);
    }

    /**
     * Creates new collection.
     * Note: Mongo creates new collections automatically on the first demand,
     * this method makes sense only for the migration script or for the case
     * you need to create collection with the specific options.
     * @param string $name name of the collection
     * @param array $options collection options in format: "name" => "value"
     * @param array $execOptions -> goto Command::execute()
     * @return bool whether operation was successful.
     * @throws Exception on failure.
     */
    public function createCollection(string $name, array $options = [], array $execOptions = []): bool
    {
        return $this->createCommand()->createCollection($name, $options, $execOptions);
    }

    /**
     * Drops specified collection.
     * @param string $name name of the collection
     * @param array $execOptions -> goto Command::execute()
     * @return bool whether operation was successful.
     * @since 2.1
     */
    public function dropCollection(string $name, array $execOptions = []): bool
    {
        return $this->createCommand()->dropCollection($name, $execOptions);
    }

    /**
     * Returns the list of available collections in this database.
     * @param array $condition filter condition.
     * @param array $options options list.
     * @param array $execOptions -> goto Command::execute()
     * @return array collections information.
     * @since 2.1.1
     */
    public function listCollections(array $condition = [], array $options = [], array $execOptions = []): array
    {
        return $this->createCommand()->listCollections($condition, $options, $execOptions);
    }

    /**
     * Clears internal collection lists.
     * This method can be used to break cycle references between [[Database]] and [[Collection]] instances.
     */
    public function clearCollections(): void
    {
        $this->_collections = [];
        $this->_fileCollections = [];
    }
}
