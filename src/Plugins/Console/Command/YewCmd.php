<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Yew\Core\Context\Context;
use Yew\Framework\Console\Exception;
use Yew\Framework\Console\UnknownCommandException;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Plugins\Console\ConsolePlugin;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class YewCmd extends Command
{
    /**
     * @var Context
     */
    private Context $context;

    /**
     * @var Application 
     */
    private Application $app;

    /**
     * StartCmd constructor.
     * @param Context $context
     * @throws InvalidConfigException
     */
    public function __construct(Context $context)
    {
        parent::__construct();
        $this->context = $context;
        $this->app = Application::instance();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('yew')->setDescription("Yew console");
        $this->addArgument('route', InputArgument::REQUIRED, 'Route');
        $this->addArgument('argv', InputArgument::IS_ARRAY, 'argv');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception\Exception
     * @throws Exception\UnknownCommandException
     * @throws \ReflectionException
     * @throws \Yew\Framework\Exception\Exception
     * @throws \Yew\Framework\Exception\InvalidConfigException
     * @throws \Yew\Framework\Exception\InvalidRouteException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arguments = $input->getArguments();

        unset($arguments['command'], $arguments['route']);

        $argumentValues = array_values($arguments);

        $prettyArguments = $argumentValues[0];

        $route = $input->getArgument('route');

        Application::instance()->runAction($route, $prettyArguments);

        $io = new SymfonyStyle($input, $output);

        //$io->success(sprintf("Route %s execute success", $route));

        return ConsolePlugin::SUCCESS_EXIT;
    }
}