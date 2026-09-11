<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Console\Command;

use Yew\Core\Context\Context;
use Yew\Plugins\Console\ConsolePlugin;
use Yew\Coroutine\Server\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StopCmd extends Command
{
    /**
     * @var Context
     */
    private $context;

    /**
     * StartCmd constructor.
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct();
        $this->context = $context;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName("stop")->setDescription("Stop(Kill) server");
        $this->addOption("kill", "k", InputOption::VALUE_NONE, "Kill server?");
    }

    /**
     * @inheritDoc
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $serverName = Server::$instance->getServerConfig()->getName();

        // The master shows up as "<serverName>-master" in ps; grab its pid.
        $masterPid = (int) trim(exec("ps -ef | grep $serverName-master | grep -v 'grep ' | awk '{print $2}'"));
        if ($masterPid <= 0) {
            $io->warning("server $serverName not run");
            return ConsolePlugin::SUCCESS_EXIT;
        }

        // Use ps to check liveness, not kill(pid, 0) — a zombie still answers 0,
        // so kill() would lie and we'd think the server is still up.
        $isAlive = function (int $pid): bool {
            return $pid > 0 && trim(exec("ps -p $pid -o comm= 2>/dev/null")) !== '';
        };

        // --kill: just nuke the whole group and get out.
        if ($input->getOption("kill")) {
            posix_kill(-$masterPid, SIGKILL);
            // small pause so we don't report success while it's still dying
            for ($i = 0; $i < 10 && $isAlive($masterPid); $i++) {
                usleep(200000);
            }
            if ($isAlive($masterPid)) {
                $io->warning("Server $serverName stop fail");
                return ConsolePlugin::FAIL_EXIT;
            }
            $io->success("Server $serverName stop success (force killed)");
            return ConsolePlugin::SUCCESS_EXIT;
        }

        // Normal path: the master listens for SIGTERM (see _onStart) and calls
        // Swoole's shutdown(), which lets every worker clean up first. Send once,
        // then just wait.
        $killRet = posix_kill($masterPid, SIGTERM);

        // Give it a few seconds (reload_async + max_wait_time is 3s). If it's
        // still kicking after 8s, kill the whole group.
        $graceTimeout = 8;
        $start = time();
        $forced = false;
        while ($isAlive($masterPid)) {
            if (time() - $start >= $graceTimeout) {
                $io->warning("Graceful shutdown timed out, force killing process group $masterPid");
                posix_kill(-$masterPid, SIGKILL);
                $forced = true;
                for ($i = 0; $i < 10 && $isAlive($masterPid); $i++) {
                    usleep(200000);
                }
                if ($isAlive($masterPid)) {
                    $io->warning("Server $serverName stop fail");
                    return ConsolePlugin::FAIL_EXIT;
                }
                break;
            }
            usleep(20000);
        }

        $io->success("Server $serverName stop success" . ($forced ? " (force killed)" : " (graceful)"));
        return ConsolePlugin::SUCCESS_EXIT;
    }
}