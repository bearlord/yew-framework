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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RestartCmd extends Command
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
        $this->setName('restart')->setDescription("Restart server");
        $this->addOption('clearCache', "c", InputOption::VALUE_NONE, 'Who do you want to clear cache?');
    }

    /**
     * @inheritDoc
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Yew\Core\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $serverConfig = Server::$instance->getServerConfig();

        $serverName = $serverConfig->getName();
        $masterPid = exec("ps -ef | grep $serverName-master | grep -v 'grep ' | awk '{print $2}'");
        if (empty($masterPid)) {
            $io->warning(sprintf("Server %s is not running", $serverName));
            return ConsolePlugin::SUCCESS_EXIT;
        }

        $command = $this->getApplication()->find('stop');
        $arguments = array(
            'command' => 'stop'
        );
        $greetInput = new ArrayInput($arguments);
        $code = $command->run($greetInput, $output);
        if ($code == ConsolePlugin::FAIL_EXIT) {
            return ConsolePlugin::FAIL_EXIT;
        }
        if ($input->getOption('clearCache')) {
            $io->note("Clear cache file");

            $serverConfig = Server::$instance->getServerConfig();
            if (file_exists($serverConfig->getCacheDir() . "/aop")) {
                clearDir($serverConfig->getCacheDir() . "/aop");
            }
            if (file_exists($serverConfig->getCacheDir() . "/di")) {
                clearDir($serverConfig->getCacheDir() . "/di");
            }
            if (file_exists($serverConfig->getCacheDir() . "/proxies")) {
                clearDir($serverConfig->getCacheDir() . "/proxies");
            }
        }
        $serverConfig->setDaemonize(true);
        return ConsolePlugin::NOEXIT;
    }
}
