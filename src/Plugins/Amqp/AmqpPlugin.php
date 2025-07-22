<?php
/**
 * Yew framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Amqp;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugins\Config\ConfigException;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Server\Coroutine\Server;
use Yew\Plugins\Amqp\AmqpPool;
use Yew\Plugins\Amqp\AmqpPools;

class AmqpPlugin extends AbstractPlugin
{
    use GetLogger;

    use GetAmqp;

    /**
     * @var Configs
     */
    protected $configs;
    

    /**
     * AmqpPlugin constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->configs = new Configs();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "AmqpPlugin";
    }

    /**

     * @param Context $context
     * @throws ConfigException
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        $configs = Server::$instance->getConfigContext()->get("yew.amqp");

        foreach ($configs as $key => $config) {
            $configObject = new Config($key);
            $this->configs->addConfig($configObject->buildFromConfig($config));
        }
    }

    /**

     * @param Context $context
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        $pools = new AmqpPools();

        $configs = $this->configs->getConfigs();
        if (empty($configs)) {
            $this->warn("Amqp configuration not found");
            return false;
        }

        foreach ($configs as $key => $config) {
            $pool = new AmqpPool($config);
            $pools->addPool($pool);
            $this->debug(sprintf("Amqp connection pool named %s created", $config->getName()));
        }

        $context->add("amqpPools", $pools);
        $this->setToDIContainer(AmqpPools::class, $pools);
        $this->setToDIContainer(AmqpPool::class, $pools->getPool());

        $this->ready();
    }
}