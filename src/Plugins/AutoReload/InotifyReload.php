<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AutoReload;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\AutoReload\AutoReloadConfig;


class InotifyReload
{
    use GetLogger;

    public string $monitorDirectory;

    public $inotifyFd;

    /**
     * InotifyReload constructor.
     * @param AutoReloadConfig $autoReloadConfig
     * @throws \Exception
     */
    public function __construct(AutoReloadConfig $autoReloadConfig)
    {
        $this->prepareInit($autoReloadConfig);
    }

    /**
     * @param AutoReloadConfig $autoReloadConfig
     * @throws \Exception
     */
    public function prepareInit(AutoReloadConfig $autoReloadConfig)
    {
        if ($autoReloadConfig->isEnable()) {
            $this->info('Hot reload is enabled');

            $this->monitorDirectory = realpath($autoReloadConfig->getMonitorDir());
            if (!extension_loaded('inotify')) {
                addTimerAfter(1000, [$this, 'unUseInotify']);
            } else {
                $this->useInotify();
            }
        }
    }

    /**
     * Use inotify
     */
    public function useInotify()
    {
        global $monitorFiles;

        $this->inotifyFd = inotify_init();
        stream_set_blocking($this->inotifyFd, 0);

        $dir_iterator = new \RecursiveDirectoryIterator($this->monitorDirectory);
        $iterator = new \RecursiveIteratorIterator($dir_iterator);
        foreach ($iterator as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }

            $wd = inotify_add_watch($this->inotifyFd, $file, IN_MODIFY);
            $monitorFiles[$wd] = $file;
        }

        swoole_event_add($this->inotifyFd, function ($inotify_fd) {
            global $monitorFiles;

            $events = inotify_read($inotify_fd);
            if ($events) {
                foreach ($events as $ev) {
                    if (!array_key_exists($ev['wd'], $monitorFiles)) {
                        continue;
                    }

                    $file = $monitorFiles[$ev['wd']];
                    $this->deleteCache($file);

                    $this->info("RELOAD $file update");

                    unset($monitorFiles[$ev['wd']]);
                    if (is_file($file)) {
                        $wd = inotify_add_watch($inotify_fd, $file, IN_MODIFY);
                        $monitorFiles[$wd] = $file;
                    }
                }
                Server::$instance->reload();
            }
        }, null, SWOOLE_EVENT_READ);
    }

    /**
     * Unuse inotify
     *
     * @throws \Exception
     */
    public function unUseInotify()
    {
        $this->warn("Non-inotify mode, performance is extremely low, it is not recommended to enable it in a formal environment. Please install inotify extension");
        if (Process::isDarwin()) {
            $this->warn("Mac auto_reload may cause excessive CPU usage");
        }
        addTimerTick(1, function () {
            global $lastMtime;
            // recursive traversal directory
            $dirIterator = new \RecursiveDirectoryIterator($this->monitorDirectory);

            $iterator = new \RecursiveIteratorIterator($dirIterator);

            foreach ($iterator as $file) {
                //Only check php files
                if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                    continue;
                }

                if (!isset($lastMtime)) {
                    $lastMtime = $file->getMTime();
                }

                //Check mtime
                if ($lastMtime < $file->getMTime()) {
                    $this->deleteCache($file);
                    $this->info("Reload $file update");

                    //reload
                    Server::$instance->reload();

                    $lastMtime = $file->getMTime();
                    break;
                }
            }
        });
    }

    /**
     * Delete cache
     *
     * @param $file
     * @throws \Yew\Core\Exception\Exception
     */
    private function deleteCache($file)
    {
        $cacheDir = Server::$instance->getServerConfig()->getCacheDir() . "/aop";

        $rootDir = realpath(Server::$instance->getServerConfig()->getRootDir());
        $aopFile = str_replace($rootDir, $cacheDir, $file);

        if (is_file($aopFile)) {
            unlink($aopFile);
        }
    }
}
