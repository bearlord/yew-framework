<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\ActorCacheProcess;
use Yew\Plugins\Ipc\IpcPlugin;

class ActorPlugin extends AbstractPlugin
{

    /**
     * @var ActorConfig|null
     */
    private ?ActorConfig $actorConfig;

    /**
     * @var ActorManager
     */
    protected ActorManager $actorManager;

    public function __construct()
    {
        parent::__construct();

        $this->initConfig();

        $this->atAfter(IpcPlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new IpcPlugin());
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "Actor";
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        $this->actorConfig->merge();
        for ($i = 0; $i < $this->actorConfig->getActorWorkerCount(); $i++) {
            Server::$instance->addProcess("actor-$i", ActorProcess::class, ActorConfig::GROUP_NAME);
        }
		
        $this->actorManager = ActorManager::getInstance();
        return;
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }

	/**
	 * @return void
	 */
	protected function initConfig()
	{
		$config = Server::$instance->getConfigContext()->get("yew.actor");

		$actorConfig = new ActorConfig();

		$actorConfig->setMaxCount($config["maxCount"]);

		$actorConfig->setMailboxCapacity($config["maxClassCount"]);

		$actorConfig->setWorkerCount($config["workerCount"]);

		$actorConfig->setMaxClassCount($config["maxClassCount"]);

		$this->actorConfig = $actorConfig;
	}
}