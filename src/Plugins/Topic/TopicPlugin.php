<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Topic;

use Yew\Core\Context\Context;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Exception\ConfigException;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Database\DatabasePlugin;
use Yew\Plugins\Redis\RedisPlugin;
use Yew\Plugins\Topic\Aspect\TopicAspect;
use Yew\Plugins\Topic\Storage\DriverFactory;
use Yew\Plugins\Topic\Storage\DriverInterface;
use Yew\Plugins\Topic\Storage\DriverStrategy;
use Yew\Plugins\Topic\Storage\StorageFactory;
use Yew\Plugins\Uid\UidConfig;
use Yew\Plugins\Uid\UidPlugin;


class TopicPlugin extends AbstractPlugin
{
    const PROCESS_GROUP_NAME = "HelperGroup";

    /**
     * @var Table
     */
    protected Table $topicTable;

    /**
     * @var TopicConfig
     */
    private TopicConfig $topicConfig;

    /**
     * @param TopicConfig|null $topicConfig
     */
    public function __construct()
    {
        parent::__construct();

        $this->initConfig();

        $this->atAfter(UidPlugin::class);
        $this->atAfter(RedisPlugin::class);
        $this->atAfter(DatabasePlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new UidPlugin());
    }

    /**
     * @param Context $context
     * @return void
     */
    public function init(Context $context)
    {
        parent::init($context);

        $aopConfig = DIGet(AopConfig::class);

        $topicAspect = new TopicAspect();
        $aopConfig->addAspect($topicAspect);
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Topic";
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        $this->topicConfig->merge();

        $this->createTopicTable();

        Server::$instance->addProcess($this->topicConfig->getProcessName(), TopicProcess::class, self::PROCESS_GROUP_NAME);
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() == $this->topicConfig->getProcessName()) {
            $driver = StorageFactory::create($this->topicConfig->getStorage());

            $topic = new Topic($driver);
            $this->setToDIContainer(Topic::class, $topic);
        }

        $this->ready();
    }


    /**
     * Init config
     * @return void
     */
    protected function initConfig()
    {
        $topicConfig = new TopicConfig();

        $config = Server::$instance->getConfigContext()->get("yew.topic");
        if (!empty($config["storage"])) {
            $topicConfig->setStorage($config["storage"]);
        }

        $this->topicConfig = $topicConfig;
    }

    /**
     * Create topic table
     * @return void
     */
    protected function createTopicTable()
    {
        $uidConfig = DIGet(UidConfig::class);
        $this->topicTable = new Table($this->topicConfig->getCacheTopicCount());
        $this->topicTable->column("topic", Table::TYPE_STRING, $this->topicConfig->getTopicMaxLength());
        $this->topicTable->column("uid", Table::TYPE_STRING, $uidConfig->getUidMaxLength());
        $this->topicTable->create();

        $this->setToDIContainer("topicTable", $this->topicTable);
    }
}