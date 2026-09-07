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

/**
 * Watches PHP files in a directory and reloads the server on change.
 * Prefers the inotify extension; falls back to directory polling when absent.
 */
class InotifyReload
{
    use GetLogger;

    private string $monitorDirectory;

    private $inotifyFd;

    /** watch-descriptor => watched file path */
    private array $monitorFiles = [];

    /** mtime recorded at the previous scan, used to detect changes */
    private int $lastScannedMtime = 0;

    public function __construct(AutoReloadConfig $autoReloadConfig)
    {
        $this->prepareInit($autoReloadConfig);
    }

    public function prepareInit(AutoReloadConfig $autoReloadConfig)
    {
        if (!$autoReloadConfig->isEnable()) {
            return;
        }

        $this->info("Hot reload is enabled");

        $this->monitorDirectory = realpath($autoReloadConfig->getMonitorDir());
        if (!extension_loaded("inotify")) {
            addTimerAfter(1000, [$this, "unUseInotify"]);
        } else {
            $this->useInotify();
        }
    }

    public function useInotify()
    {
        $this->inotifyFd = inotify_init();
        stream_set_blocking($this->inotifyFd, 0);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->monitorDirectory)
        );
        foreach ($iterator as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== "php") {
                continue;
            }
            $wd = inotify_add_watch($this->inotifyFd, $file, IN_MODIFY);
            $this->monitorFiles[$wd] = $file;
        }

        swoole_event_add($this->inotifyFd, function ($inotifyFd) {
            $events = inotify_read($inotifyFd);
            if (!$events) {
                return;
            }
            foreach ($events as $ev) {
                if (!array_key_exists($ev["wd"], $this->monitorFiles)) {
                    continue;
                }
                $file = $this->monitorFiles[$ev["wd"]];
                $this->deleteCache($file);
                $this->info("RELOAD $file update");
                unset($this->monitorFiles[$ev["wd"]]);
                if (is_file($file)) {
                    $wd = inotify_add_watch($inotifyFd, $file, IN_MODIFY);
                    $this->monitorFiles[$wd] = $file;
                }
            }
            Server::$instance->reload();
        }, null, SWOOLE_EVENT_READ);
    }

    public function unUseInotify()
    {
        $this->warn("Non-inotify mode, performance is extremely low, it is not recommended to enable it in a formal environment. Please install inotify extension");
        if (Process::isDarwin()) {
            $this->warn("Mac auto_reload may cause excessive CPU usage");
        }
        addTimerTick(1, function () {
            $dirIterator = new \RecursiveDirectoryIterator($this->monitorDirectory);
            $iterator = new \RecursiveIteratorIterator($dirIterator);

            $maxMtime = $this->lastScannedMtime;
            $changed = null;
            foreach ($iterator as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== "php") {
                    continue;
                }
                $mtime = $file->getMTime();
                if ($mtime > $maxMtime) {
                    $maxMtime = $mtime;
                    $changed = $file;
                }
            }
            if ($changed !== null && $maxMtime > $this->lastScannedMtime) {
                $this->lastScannedMtime = $maxMtime;
                $this->deleteCache($changed);
                $this->info("Reload $changed update");
                Server::$instance->reload();
            }
        });
    }

    private function deleteCache($file)
    {
        $cacheDir = Server::$instance->getServerConfig()->getCacheDir() . "/aop";
        $rootDir = realpath(Server::$instance->getServerConfig()->getRootDir());
        if ($rootDir === false || $file === null) {
            return;
        }
        $aopFile = str_replace($rootDir, $cacheDir, $file);
        if (is_file($aopFile)) {
            unlink($aopFile);
        }
    }
}
