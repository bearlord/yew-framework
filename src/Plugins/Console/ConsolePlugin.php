<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Console;

use Exception;
use ReflectionException;
use Swoole\Event;
use Symfony\Component\Finder\Finder;
use Yew\Core\Context\Context;
use Yew\Core\Exception\ConfigException;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Plugins\Console\Command\ReloadCmd;
use Yew\Plugins\Console\Command\RestartCmd;
use Yew\Plugins\Console\Command\StartCmd;
use Yew\Plugins\Console\Command\StatusCmd;
use Yew\Plugins\Console\Command\StopCmd;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class ConsolePlugin
 * @package Yew\Plugins\Console
 */
class ConsolePlugin extends AbstractPlugin
{
    const SUCCESS_EXIT = 0;

    const FAIL_EXIT = 1;

    const NOEXIT = -255;

    /**
     * @var Application
     */
    private Application $application;

    /**
     * @var ConsoleConfig|null
     */
    private ?ConsoleConfig $config;

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Console";
    }

    /**
     * @param ConsoleConfig|null $config
     */
    public function __construct(?ConsoleConfig $config = null)
    {
        parent::__construct();

        if ($config == null) {
            $config = new ConsoleConfig();
        }

        $this->config = $config;

        $this->application = new Application("Yew");
        $this->application->setAutoExit(false);
    }

    /**
     * @param Context $context
     * @return void
     * @throws Exception
     */
    public function init(Context $context)
    {
        parent::init($context);
        enableRuntimeCoroutine(false);

        $input = new ArgvInput();
        $output = new ConsoleOutput();

        $this->addCoreCmdClass();

        $this->addCustomCmdClass();

        $this->addCommands($context);

        $exitCode = $this->application->run($input, $output);

        if ($exitCode >= 0) {
            Event::exit();
            exit();
        }

        enableRuntimeCoroutine();
    }

    /**
     * @var array|string[]
     */
    protected array $coreCommands = [
        ReloadCmd::class,
        RestartCmd::class,
        StartCmd::class,
        StatusCmd::class,
        StopCmd::class,
    ];

    /**
     * @return void
     * @throws ReflectionException
     * @throws ConfigException
     */
    protected function addCoreCmdClass()
    {
        foreach ($this->coreCommands as $cmd) {
            $this->config->addCmdClass($cmd);
        }

        $this->config->merge();
    }

    /**
     * @return array|null
     */
    protected function getCustomCommandClass()
    {
        $directory = ROOT_DIR . "src/Command";
        if (!file_exists($directory)) {
            return null;
        }

        $finder = new Finder();
        $finder->files()
            ->in($directory)
            ->name('*Command.php')
            ->ignoreDotFiles(true);

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getBasename(".php");
        }

        return $files;
    }

    /**
     * @return void
     * @throws ConfigException
     * @throws ReflectionException
     */
    protected function addCustomCmdClass()
    {
        $namespacePrefix = "App\\Command\\";

        $customerCommandClass = $this->getCustomCommandClass();
        if (empty($customerCommandClass)) {
            return;
        }

        foreach ($customerCommandClass as $cmd) {
            $this->config->addCmdClass($namespacePrefix . $cmd);
        }

        $this->config->merge();
    }

    /**
     * @param Context $context
     * @return void
     */
    protected function addCommands(Context $context)
    {
        $commands = [];
        foreach ($this->config->getCmdClassList() as $value) {
            $cmd = new $value($context);
            if ($cmd instanceof Command) {
                $commands[$cmd->getName()] = $cmd;
            }
        }
        $this->application->addCommands($commands);
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
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
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->application;
    }
}