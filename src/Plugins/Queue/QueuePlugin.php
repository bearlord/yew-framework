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
     * @param Context $context
     * @return mixed|void
     */
    public function beforeServerStart(Context $context)
    {
        $this->config = Server::$instance->getConfigContext()->get("yii.queue");
        if (empty($this->config)) {
	        $this->warn("Queue configuration not found");
            return false;
        }
        
        Server::$instance->addProcess(self::PROCESS_NAME, HelperQueueProcess::class, self::PROCESS_GROUP_NAME);

        //Add custom queue process
        $index = 0;
        foreach ($this->config as $key => $config) {
            Server::$instance->addProcess(self::PROCESS_QUEUE_PREFIX . $index, QueueProcess::class, QueueTask::GROUP_NAME);
            $index++;
        }
    }

    /**
     * @param Context $context
     * @return mixed|void
     */
    public function beforeProcessStart(Context $context)
    {
        $pools = new QueuePools();

        if (empty($this->config)) {
            return false;
        }

        $index = 0;
        foreach ($this->config as $key => $config) {
            if (empty($config["minIntervalTime"]) || $config["minIntervalTime"] < 1000) {
                $config["minIntervalTime"] = 1000;
            }

            $pool = new QueuePool($key, $config);
            $pools->addPool($key, $pool);

            /** @var Queue $queue */
            $queue = $pool->handle();

            //Custom process
            if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() === self::PROCESS_QUEUE_PREFIX . $index) {
                $queue->listen();
            }
            $index++;
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