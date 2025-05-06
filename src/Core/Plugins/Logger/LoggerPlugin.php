<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;

use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use ReflectionException;
use Yew\Core\Context\Context;
use Yew\Core\DI\DI;
use Yew\Core\Exception\ConfigException;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugins\Config\ConfigChangeEvent;
use Yew\Core\Plugins\Config\ConfigPlugin;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Server\Server;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

class LoggerPlugin extends AbstractPlugin
{
    /**
     * @var Logger
     */
    private Logger $logger;
    /**
     * @var LoggerConfig|null
     */
    private ?LoggerConfig $loggerConfig;
    /**
     * @var GoSwooleProcessor
     */
    private GoSwooleProcessor $goSwooleProcessor;

    /**
     * LoggerPlugin constructor.
     * @param LoggerConfig|null $loggerConfig
     */
    public function __construct(?LoggerConfig $loggerConfig = null)
    {
        parent::__construct();
        $this->atAfter(ConfigPlugin::class);
        if ($loggerConfig == null) {
            $loggerConfig = new LoggerConfig();

        }
        $this->loggerConfig = $loggerConfig;
    }

    /**
     * @param Context $context
     * @throws Exception
     */
    private function buildLogger(Context $context)
    {
        $this->logger = new Logger($this->loggerConfig->getName());
        $formatter = new LineFormatter($this->loggerConfig->getOutput(),
            $this->loggerConfig->getDateFormat(),
            $this->loggerConfig->isAllowInlineLineBreaks(),
            $this->loggerConfig->isIgnoreEmptyContextAndExtra());

        //Screen print
        $handler = new StreamHandler('php://stderr', $this->loggerConfig->getLevel());
        $handler->setFormatter($formatter);

        $this->logger->pushHandler($handler);

        $this->goSwooleProcessor = new GoSwooleProcessor($this->loggerConfig->isColor());
        $this->logger->pushProcessor($this->goSwooleProcessor);
        $this->logger->pushProcessor(new GoIntrospectionProcessor());

        DI::getInstance()->set(LoggerInterface::class, $this->logger);
        DI::getInstance()->set(\Monolog\Logger::class, $this->logger);
        DI::getInstance()->set(Logger::class, $this->logger);
    }

    /**
     * @param Context $context
     * @return void
     * @throws ConfigException
     * @throws ReflectionException
     */
    public function beforeServerStart(Context $context)
    {
        $this->loggerConfig->merge();

        $this->buildLogger($context);

        foreach ($this->logger->getHandlers() as $handler) {
            if ($handler instanceof AbstractHandler) {
                $handler->setLevel($this->loggerConfig->getLevel());
            }
        }

        $this->goSwooleProcessor->setColor($this->loggerConfig->isColor());
    }

    /**
     * @param Context $context
     * @return void
     * @throws ConfigException
     * @throws ReflectionException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function beforeProcessStart(Context $context)
    {
        $serverConfig = Server::$instance->getServerConfig();
        if (Server::$instance->getServerConfig()->isDaemonize()) {
            //Remove screen print handler
            $this->logger->popHandler();

            //Add a log handler
            $handler = new RotatingFileHandler($serverConfig->getLogDir() . DIRECTORY_SEPARATOR . $this->loggerConfig->getName() . ".log",
                $this->loggerConfig->getMaxFiles(),
                $this->loggerConfig->getLevel());
            $this->logger->pushHandler($handler);
            $this->goSwooleProcessor->setColor(false);
        }

        //Monitoring configuration updates
        goWithContext(function () use ($context) {
            $eventDispatcher = DI::getInstance()->get(EventDispatcher::class);

            $call = $eventDispatcher->listen(ConfigChangeEvent::ConfigChangeEvent);
            $call->call(function ($result) {
                $this->loggerConfig->merge();
                foreach ($this->logger->getHandlers() as $handler) {
                    if ($handler instanceof AbstractHandler) {
                        $handler->setLevel($this->loggerConfig->getLevel());
                    }
                }
            });
        });
        $this->ready();
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getName(): string
    {
        return "Logger";
    }

    /**
     * Get logger
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }
}