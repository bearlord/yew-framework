<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Database;

use Yew\Coroutine\Server\Server;
use Yew\Framework\Db\Connection;
use Yew\Yew;

trait GetDatabase
{
    /**
     * @param string|null $name
     * @return mixed|Connection|null
     * @throws \Throwable
     * @throws \Yew\Framework\Db\Exception
     */
    public function db(?string $name = "default")
    {
        $subName = "";
        if (strpos($name, ".") > 0) {
            list($name, $subName) = explode(".", $name, 2);
        }

        switch ($subName) {
            case "slave":
            case "master":
                $_configKey = sprintf("db.%s.%s", $name, $subName);
                $_configs = Server::$instance->getConfigContext()->get($_configKey);

                if (empty($_configs)) {
                    $poolKey = $name;
                    $contextKey = sprintf("db:%s", $name);
                } else {
                    $_randKey = array_rand($_configs);

                    $poolKey = sprintf("%s.%s.%s", $name, $subName, $_randKey);
                    $contextKey = sprintf("db:%s.%s.%s", $name, $subName, $_randKey);
                }
                break;

            default:
                $poolKey = $name;
                $contextKey = sprintf("db:%s", $name);
                break;
        }

        $db = getContextValue($contextKey);
        if (!empty($db)) {
            return $db;
        }

        /** @var DatabasePools $pdoPools */
        $pdoPools = getDeepContextValueByClassName(DatabasePools::class);
        if (!empty($pdoPools)) {
            /** @var DatabasePool $pool */
            $pool = $pdoPools->getPool($poolKey);
            if ($pool == null) {
                Server::$instance->getLog()->error("No Pdo connection pool named {$poolKey} was found");
                throw new \PDOException("No Pdo connection pool named {$poolKey} was found");
            }

            try {
                $db = $pool->db();
                if (empty($db)) {
                    Server::$instance->getLog()->error("Empty db, get db once.");
                    return $this->getDbOnce($name);
                }
                return $db;
            } catch (\Exception $e) {
                Server::$instance->getLog()->error($e);
                throw $e;
            }

        } else {
            return $this->getDbOnce($name);
        }
    }

    /**
     * @param $name
     * @return Connection|null
     * @throws \Yew\Framework\Db\Exception
     */
    public function getDbOnce($name): ?Connection
    {
        $contextKey = sprintf("db:%s", $name);
        $db = getContextValue($contextKey);
        if (!empty($db)) {
            return $db;
        }

        $_configKey = sprintf("db.%s", $name);
        $_config = Server::$instance->getConfigContext()->get($_configKey);
        $db = new Connection([
            'poolName' => $name,
            'dsn' => $_config['dsn'],
            'username' => $_config['username'],
            'password' => $_config['password'],
            'charset' => $_config['charset'] ?? 'utf8',
            'tablePrefix' => $_config['tablePrefix'],
            'enableSchemaCache' => $_config['enableSchemaCache'],
            'schemaCacheDuration' => $_config['schemaCacheDuration'],
            'schemaCache' => $_config['schemaCache'],
        ]);
        $db->open();
        setContextValue($contextKey, $db);

        return $db;
    }
}
