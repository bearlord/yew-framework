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
use Yew\Plugins\Topic\Aspect\TopicAspect;
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
    public function __construct(?TopicConfig $topicConfig = null)
    {
        parent::__construct();

        if ($topicConfig == null) {
            $topicConfig = new TopicConfig();
        }

        $this->topicConfig = $topicConfig;

        $this->atAfter(UidPlugin::class);
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
     * @throws \Exception
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
     * @throws ConfigException
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function beforeServerStart(Context $context)
    {
        $this->topicConfig->merge();
        $uidConfig = DIGet(UidConfig::class);

        $this->topicTable = new Table($this->topicConfig->getCacheTopicCount());
        $this->topicTable->column("topic", Table::TYPE_STRING, $this->topicConfig->getTopicMaxLength());
        $this->topicTable->column("uid", Table::TYPE_STRING, $uidConfig->getUidMaxLength());
        $this->topicTable->create();

        Server::$instance->addProcess($this->topicConfig->getProcessName(), TopicProcess::class, self::PROCESS_GROUP_NAME);
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() == $this->topicConfig->getProcessName()) {
            $topic = new Topic($this->topicTable);
            $this->setToDIContainer(Topic::class, $topic);
        }
        $this->ready();
    }
}