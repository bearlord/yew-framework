<?php

namespace Yew\Plugins\Topic\Storage;

use Yew\Core\Memory\CrossProcess\Table;
use Yew\Plugins\Topic\Storage\Db\DbDriver;
use Yew\Plugins\Topic\Storage\Memory\MemoryDriver;

class DriverFactory
{
    /**
     * Create and initialize a storage driver based on config
     *
     * @param array $config
     * @param Table $topicTable
     * @return DriverInterface
     */
    public static function create(array $config, Table $topicTable): DriverInterface
    {
        $type = $config["type"] ?? "memory";

        switch ($type) {
            case "db":
                $driver = new DbDriver();
                break;


            case "memory":
            default:
                $driver = new MemoryDriver();
        }

        $driver->init();

        return $driver;
    }
}
