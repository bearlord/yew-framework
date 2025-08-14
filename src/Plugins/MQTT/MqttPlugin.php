<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\MQTT;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Plugins\MQTT\Auth\MqttAuth;
use Yew\Plugins\Pack\PackPlugin;
use Yew\Plugins\Topic\TopicPlugin;
use Yew\Plugins\Uid\UidPlugin;

class MqttPlugin extends AbstractPlugin
{
    /**
     * @var MqttPluginConfig
     */
    private $mqttPluginConfig;

    /**
     * @param MqttPluginConfig|null $mqttPluginConfig
     * @throws \ReflectionException
     */
    public function __construct(?MqttPluginConfig $mqttPluginConfig = null)
    {
        parent::__construct();
        $this->atBefore(PackPlugin::class);
        if ($mqttPluginConfig == null) {
            $mqttPluginConfig = new MqttPluginConfig();
        }
        $this->mqttPluginConfig = $mqttPluginConfig;
    }
    
    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new UidPlugin());
        $pluginInterfaceManager->addPlugin(new TopicPlugin());
        $pluginInterfaceManager->addPlugin(new PackPlugin());
    }
    
    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     */
    public function init(Context $context)
    {
        parent::init($context);
        $this->mqttPluginConfig->merge();
        $authRc = new \ReflectionClass($this->mqttPluginConfig->getMqttAuthClass());
        $authAmpl = $authRc->newInstance();
        $this->setToDIContainer(MqttAuth::class, $authAmpl);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "MQTT";
    }

    /**
     * @param Context $context
     */
    public function beforeServerStart(Context $context)
    {
        return;
    }

    /**
     * @param Context $context
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}
