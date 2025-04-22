<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Console\Command;

use Yew\Core\Context\Context;
use Yew\Core\Server\Config\PortConfig;
use Yew\Core\Server\Version;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Console\ConsolePlugin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCmd extends Command
{
    /**
     * @var Context
     */
    private $context;

    protected $config;

    /**
     * StartCmd constructor.
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct();
        $this->context = $context;
        $this->config = Server::$instance->getConfigContext();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('status')->setDescription("Server Status");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \ReflectionException
     * @throws \Yew\Core\Exception\ConfigException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $server_name = $this->config->get('yew.server.name') ?? 'Yew';
        $master_pid = exec("ps -ef | grep $server_name-master | grep -v 'grep ' | awk '{print $2}'");
        $io->title('WELCOME TO Yew-FRAMEWORK!');
        $io->table(
            [
                "System",
                "PHP Version",
                "Swoole Version",
                "Yew Version",
                "Worker Num",
            ],
            [
                [
                    PHP_OS,
                    PHP_VERSION,
                    SWOOLE_VERSION,
                    Version::getVersion(),
                    $this->config->get('yew.server.workerNum', 0),
                ]
            ]
        );
        $io->section('Port Information');

        foreach (Server::$instance->getPortManager()->getPortConfigs() as $key => $portConfig) {
            $protocol = "http";
            $ssl = "";
            if ($portConfig->isOpenHttpProtocol()) {
                $protocol = "http";
                $ssl = "false";
                if ($portConfig->isEnableSsl()) {
                    $protocol = "https";
                    $ssl = "true";
                }
            } elseif ($portConfig->isOpenWebsocketProtocol()) {
                $protocol = "ws";
                $ssl = "false";
                if ($portConfig->isEnableSsl()) {
                    $protocol = "wss";
                    $ssl = "true";
                }
            } elseif ($portConfig->isOpenMqttProtocol()) {
                $protocol = "mqtt";
            } elseif ($portConfig->getSockType() == PortConfig::SWOOLE_SOCK_TCP || $portConfig->getSockType() == PortConfig::SWOOLE_SOCK_TCP6) {
                $protocol = "tcp";
            }

            $show[] = [
                $protocol,
                $key,
                $portConfig->getHost(),
                $portConfig->getPort(),
                $ssl
            ];

        }

        $io->table(
            ['TYPE', 'NAME', 'HOST', 'PORT', 'SSL'],
            $show
        );
        if (!empty($master_pid)) {
            $io->note("$server_name server already running");
        } else {
            $io->note("$server_name server not run");
        }

        return Command::SUCCESS;
    }
}