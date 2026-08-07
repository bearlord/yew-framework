<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AutoReload;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Coroutine\Server\Server;

/**
 * Watches source files and reloads the server on change (inotify or polling).
 */
class AutoReloadPlugin extends AbstractPlugin
{
    const PROCESS_NAME = "helper";

    const PROCESS_GROUP_NAME = "HelperGroup";

    protected ?InotifyReload $inotifyReload = null;

    private ?AutoReloadConfig $autoReloadConfig = null;

    public function __construct(?AutoReloadConfig $autoReloadConfig = null)
    {
        parent::__construct();
        if ($autoReloadConfig == null) {
            $autoReloadConfig = new AutoReloadConfig();
        }
        $this->autoReloadConfig = $autoReloadConfig;
    }

    public function getName(): string
    {
        return "AutoReload";
    }

    public function beforeServerStart(Context $context)
    {
        if ($this->autoReloadConfig->getMonitorDir() == null) {
            $this->autoReloadConfig->setMonitorDir(Server::$instance->getServerConfig()->getSrcDir());
        }
        $this->autoReloadConfig->merge();

        Server::$instance->addProcess(self::PROCESS_NAME, HelperReloadProcess::class, self::PROCESS_GROUP_NAME);
    }

    public function beforeProcessStart(Context $context)
    {
        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName() === self::PROCESS_NAME) {
            $this->inotifyReload = new InotifyReload($this->autoReloadConfig);
        }
        $this->ready();
    }

    public function getInotifyReload(): ?InotifyReload
    {
        return $this->inotifyReload;
    }
}
