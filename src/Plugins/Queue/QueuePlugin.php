<?php
/**
 * Yew Queue plugin
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Queue;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Amqp\AmqpPlugin;
use Yew\Plugins\Redis\RedisPlugin;
use Yew\Core\Server\Coroutine\Server;
use Yew\Framework\Helpers\Json;
use Yew\Core\Plugins\Yew\YewPlugin;
use Yew\Plugins\Queue\Beans\QueueTask;
use Yew\Plugins\Queue\HelperQueueProcess;
use Yew\Plugins\Queue\QueueProcess;
use Yew\Framework\Queue\Drivers\Redis\Queue;
use Yew\Yew;

class QueuePlugin extends AbstractPlugin
{
    use GetLogger;

    const PROCESS_NAME = "helper";

    const PROCESS_GROUP_NAME = "HelperGroup";

    const PROCESS_QUEUE_PREFIX  = "queue-";

    /**
     * @var int
     */
    protected $taskProcessCount = 1;

    /**
     * @var array
     */
    protected $config;

    /**
     * QueuePlugin constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->atAfter(YiiPlugin::class);
        $this->atAfter(RedisPlugin::class);
        $this->atAfter(AmqpPlugin::class);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Queue";
    }

    /**
     * 注册队列相关进程(helper 进程 + 每个队列一个消费者进程)
     *
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context): void
    {
        $this->config = Server::$instance->getConfigContext()->get("yii.queue");
        if (empty($this->config)) {
            $this->warn("Queue configuration not found");
            return;
        }

        Server::$instance->addProcess(self::PROCESS_NAME, HelperQueueProcess::class, self::PROCESS_GROUP_NAME);

        foreach (array_keys($this->config) as $index => $key) {
            Server::$instance->addProcess(
                self::PROCESS_QUEUE_PREFIX . $index,
                QueueProcess::class,
                QueueTask::GROUP_NAME
            );
        }
    }

    /**
     * 构建连接池,并在当前进程对应的队列上启动监听
     *
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context): void
    {
        if (empty($this->config)) {
            return;
        }

        $pools = new QueuePools();
        $currentProcessName = Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName();

        foreach ($this->config as $name => $config) {
            $config["minIntervalTime"] = max((int) ($config["minIntervalTime"] ?? 0), 1000);

            $pool = new QueuePool((string) $name, $config);
            $pools->addPool((string) $name, $pool);

            // 仅在当前进程负责该队列时占用连接并监听,避免无谓消耗池容量
            if ($currentProcessName === self::PROCESS_QUEUE_PREFIX . $name) {
                /** @var Queue $queue */
                $queue = $pool->handle();
                $queue->listen();
            }
        }

        $context->add("QueuePools", $pools);
        $this->setToDIContainer(QueuePools::class, $pools);
        $this->ready();
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
    }
}