<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AutoReload;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\AutoReload\AutoReloadConfig;

class AutoReloadPlugin extends AbstractPlugin
{
    const PROCESS_NAME = "helper";

    const PROCESS_GROUP_NAME = "HelperGroup";

    /**
     * @var InotifyReload
     */
    protected $inotifyReload;

    /**
     * @var AutoReloadConfig|null
     */
    private ?AutoReloadConfig $autoReloadConfig = null;

    /**
     * @param AutoReloadConfig|null $autoReloadConfig
     */
    public function __construct(?AutoReloadConfig $autoReloadConfig = null)
    {
        parent::__construct();
        if ($autoReloadConfig == null) {
            $autoReloadConfig = new AutoReloadConfig();
        }
        $this->autoReloadConfig = $autoReloadConfig;
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "AutoReload";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     * @throws \Yew\Core\Exception\Exception
     */
    public function beforeServerStart(Context $context)
    {
        if ($this->autoReloadConfig->getMonitorDir() == null) {
            $this->autoReloadConfig->setMonitorDir(Server::$instance->getServerConfig()->getSrcDir());
        }
        $this->autoReloadConfig->merge();

        //Add help process
        Server::$instance->addProcess(self::PROCESS_NAME, HelperReloadProcess::class, self::PROCESS_GROUP_NAME);
        return;
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() === self::PROCESS_NAME) {
            $this->inotifyReload = new InotifyReload($this->autoReloadConfig);
        }
        $this->ready();
    }

    /**
     * @return InotifyReload
     */
    public function getInotifyReload(): InotifyReload
    {
        return $this->inotifyReload;
    }
}