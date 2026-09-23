<?php

namespace Yew\Plugins\Mqtt\Topic\Storage;

use Yew\Plugins\Mqtt\Topic\Storage\Memory\MemoryDriver;
use Yew\Plugins\Mqtt\Topic\Storage\Db\DbDriver;

/**
 * Factory for creating topic storage drivers from configuration.
 *
 * Reads the YAML storage config and builds a CompositeDriver
 * with the appropriate driver instances.
 *
 * Local copy of Yew\Plugins\Topic\Storage\StorageFactory so the Mqtt plugin
 * does not depend on the Topic plugin.
 */
class StorageFactory
{
    /**
     * Create a storage driver instance based on configuration
     *
     * @param array $config The storage configuration from YAML
     * @return DriverInterface A single driver or composite of multiple drivers
     */
    public static function create(array $config): DriverInterface
    {
        $drivers = [];

        foreach ($config as $name => $storageConfig) {
            $type = $storageConfig["type"] ?? $name;
            $driver = match ($type) {
                "memory" => new MemoryDriver(),
                "db"     => new DbDriver($storageConfig),
                default  => throw new \InvalidArgumentException("Unknown topic storage type: {$type}"),
            };

            $driver->init();

            $drivers[] = $driver;
        }

        if (count($drivers) === 1) {
            return $drivers[0];
        }

        return new CompositeDriver($drivers);
    }
}
