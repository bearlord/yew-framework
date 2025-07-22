<?php
/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Amqp;

use Yew\Coroutine\Server\Server;
use PhpAmqpLib\Connection\AbstractConnection;

trait GetAmqp
{
    /**
     * @param string $name
     * @return mixed|Connection
     * @throws \Exception
     */
    public function amqp(string $name = "default")
    {
        $poolKey = $name;
        $contextKey = sprintf("Amqp:%s", $name);

        $db = getContextValue($contextKey);
        if ($db == null) {
            /** @var AmqpPools $pdoPools * */
            $pdoPools = getDeepContextValueByClassName(AmqpPools::class);
            $pool = $pdoPools->getPool($poolKey);
            if ($pool == null) {
                throw new \Exception("No Amqp connection pool named {$poolKey} was found");
            }
            return $pool->db();
        } else {
            return $db;
        }
    }

    /**
     * @param string $name
     * @return AbstractConnection
     * @throws \Exception
     */
    public function amqpOnce(string $name = "default")
    {
        $contextKey = sprintf("Amqp:%s", $name);
        $db = getContextValue($contextKey);
        if (!empty($db)) {
            return $db;
        }

        $_configKey = sprintf("amqp.%s", $name);
        $_config = Server::$instance->getConfigContext()->get($_configKey);
        $configObject = new Config($name);
        $configObject->setValues($_config);

        $db = new AmqpConnection($configObject);
        setContextValue($contextKey, $db);

        return $db;
    }
}