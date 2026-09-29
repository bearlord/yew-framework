<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Mongodb;

use Yew\Coroutine\Server\Server;
use Yew\Framework\Mongodb\Connection;

trait GetMongodb
{
    /**
     * @param string|null $name
     * @return mixed|Connection|null
     * @throws \Throwable
     */
    public function mongodb(?string $name = "default")
    {
        $contextKey = sprintf("mongodb:%s", $name);

        $db = getContextValue($contextKey);
        if (!empty($db)) {
            return $db;
        }

        /** @var MongodbPools $mongodbPools */
        $mongodbPools = getDeepContextValueByClassName(MongodbPools::class);
        if (!empty($mongodbPools)) {
            /** @var MongodbPool $pool */
            $pool = $mongodbPools->getPool($name);
            if ($pool == null) {
                Server::$instance->getLog()->error("No Mongodb connection pool named {$name} was found");
                throw new \RuntimeException("No Mongodb connection pool named {$name} was found");
            }

            try {
                $db = $pool->mongodb();
                if (empty($db)) {
                    Server::$instance->getLog()->error("Empty mongodb, get mongodb once.");
                    return $this->getMongodbOnce($name);
                }
                return $db;
            } catch (\Exception $e) {
                Server::$instance->getLog()->error($e);
                throw $e;
            }
        }

        return $this->getMongodbOnce($name);
    }

    /**
     * @param string $name
     * @return Connection|null
     * @throws \Yew\Framework\Exception\InvalidConfigException
     */
    public function getMongodbOnce(string $name): ?Connection
    {
        $contextKey = sprintf("mongodb:%s", $name);

        $db = getContextValue($contextKey);
        if (!empty($db)) {
            return $db;
        }

        $config = Server::$instance->getConfigContext()->get("yew.mongodb.{$name}");
        if (empty($config)) {
            return null;
        }

        $db = new Connection([
            "dsn" => $config["dsn"] ?? "",
            "defaultDatabaseName" => $config["defaultDatabaseName"] ?? "",
            "options" => $config["driverOptions"] ?? []
        ]);
        $db->open();
        setContextValue($contextKey, $db);

        return $db;
    }
}
