<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AutoReload;

use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;

/**
 * Dedicated process that hosts the file watcher (InotifyReload is started in the plugin).
 */
class HelperReloadProcess extends Process
{
    public function init()
    {
    }

    public function onProcessStart()
    {
    }

    public function onProcessStop()
    {
    }

    public function onPipeMessage(Message $message, Process $fromProcess)
    {
    }
}
