<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Redis;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Redis\Exception\RedisException;
use Yew\Coroutine\Server\Server;

class RedisPlugin extends AbstractPlugin
{
    use GetLogger;

    /**
     * @var Configs
     */
    protected Configs $configs;

    /**
     * RedisPlugin constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->configs = new Configs();
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Redis";
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        ini_set('default_socket_timeout', '-1');
        foreach ($this->configs->getConfigs() as $config) {
            $config->merge();
        }

        $configs = Server::$instance->getConfigContext()->get('redis', []);
        foreach ($configs as $key => $value) {
            $configObject = new Config($key);
            $this->configs->addConfig($configObject->buildFromConfig($value));
        }

        $redisProxy = new RedisProxy();
        $this->setToDIContainer(\Redis::class, $redisProxy);
        $this->setToDIContainer(Redis::class, $redisProxy);
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        ini_set('default_socket_timeout', '-1');
        $pools = new RedisPools();

        $configs = $this->configs->getConfigs();
        if (empty($configs)) {
            $this->warn('Redis configuration not found');
            return;
        }

        /**
         * @var string $key
         * @var Config $config
         */
        foreach ($configs as $key => $config) {
            $pool = new RedisPool($config);
            $pools->addPool($pool);

            $this->debug(sprintf("%s connection pool named %s created", "Redis", $config->getName()));
        }

        $context->add("redisPool", $pools);

        $this->setToDIContainer(RedisPools::class, $pools);
        $this->setToDIContainer(RedisPool::class, $pools->getPool());

        $this->ready();
    }
}