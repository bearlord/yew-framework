<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use DI\Annotation\Inject;
use Yew\Core\Channel\Channel;
use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Plugins\Actor\Event\ActorCreateEvent;
use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Plugins\Actor\Log\LogFactory;
use Yew\Plugins\Actor\Log\Logger;
use Yew\Plugins\Actor\Mailbox\BlockStrategy;
use Yew\Plugins\Actor\Mailbox\DropStrategy;
use Yew\Plugins\Actor\Mailbox\FailStrategy;
use Yew\Plugins\Actor\Mailbox\MailboxOverflowStrategy;
use Yew\Plugins\Actor\Multicast\Multicast;
use Yew\Coroutine\Server\Server;
use Yew\Yew;
use Swoole\Timer;

abstract class Actor
{
    use GetLogger;

    /**
     * @var Multicast
     */
    protected Multicast $multicast;

    /**
     * @var MailboxOverflowStrategy Strategy applied when the mailbox is full
     */
    protected MailboxOverflowStrategy $mailboxOverflowStrategy;

    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @Inject()
     * @var EventDispatcher
     */
    protected EventDispatcher $eventDispatcher;

    /**
     * @Inject()
     * @var ActorConfig
     */
    protected ActorConfig $actorConfig;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var array data
     */
    protected array $data = [];

    /**
     * @var array timer ids
     */
    protected array $timerIds = [];

    /**
     * @var Logger
     */
    protected Logger $logHandle;

    /**
     * @var int Lifecycle state: 0=initial, 2=recovered/ready
     */
    protected int $state = 0;

    /**
     * @param string $name
     * @param bool $isCreated
     * @throws \DI\DependencyException
     */
    final public function __construct(string $name, bool $isCreated = false)
    {
        // Actors must be created only inside an actor process
        $processName = Server::$instance->getProcessManager()->getCurrentProcess()->getProcessName();
        if (stripos($processName, 'actor') === false) {
            throw new ActorException(sprintf("Actor can only be created in an actor process, current process is '%s'", $processName));
        }

        $this->name = $name;

        Server::$instance->getContainer()->injectOn($this);
        if ($isCreated) {
            ActorManager::getInstance()->addActor($this);
        }

        $this->init();

        $this->recovery();
    }

    /**
     * @return void
     */
    protected function init()
    {
        $this->iniChannel();

        //Loop process the information in the mailbox
        goWithContext(function () {
            while (true) {
                $message = $this->channel->pop();
                $this->onHandleMessage($message);
            }
        });

        $this->logHandle = LogFactory::create($this->name);

        $this->multicast = new Multicast($this->name, DIGet(\Yew\Plugins\Actor\Multicast\MulticastConfig::class));

        $saveContextTime = Server::$instance->getConfigContext()->get("actor.saveContextTime", 10);
        $this->tick($saveContextTime * 1000, [$this, "saveContext"]);
    }
    
    protected function iniChannel()
    {
        $this->channel = DIGet(Channel::class, [$this->actorConfig->getMailboxCapacity()]);
        $this->mailboxOverflowStrategy = $this->resolveOverflowStrategy($this->actorConfig->getMailboxOverflow());
    }

    /**
     * Resolve the configured overflow strategy by name.
     *
     * @param string $name One of "block", "drop", "fail"
     */
    protected function resolveOverflowStrategy(string $name): MailboxOverflowStrategy
    {
        switch (strtolower($name)) {
            case 'drop':
                return new DropStrategy();
            case 'fail':
                return new FailStrategy();
            case 'block':
            default:
                return new BlockStrategy();
        }
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @param int $state
     * @return void
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    /**
     * Init data
     * @param $data
     * @return void
     */
    public function initData($data)
    {
        $this->data = $data;
    }

    /**
     * @param ActorMessage $message
     * @return void
     */
    protected function onHandleMessage(ActorMessage $message)
    {
        $type = $message->getType();

        switch ($type) {
            case ActorMessage::TYPE_MULTICAST:
                $this->handleMulticastMessage($message);
                break;

            case ActorMessage::TYPE_COMMON:
            default:
                $this->handleMessage($message);
        }
    }

    abstract protected function handleMulticastMessage(ActorMessage $message);
    
    /**
     * @param ActorMessage $message
     * @return mixed
     */
    abstract protected function handleMessage(ActorMessage $message);

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Destroy
     */
    public function destroy()
    {
        $this->clearAllTimer();
        ActorManager::getInstance()->removeActor($this);
    }

    /**
     * Recovery
     * @return void
     */
    public function recovery()
    {
        $this->setState(2);
    }


    /**
     * Get proxy
     * @param string $actorName
     * @param bool $oneWay
     * @param float|null $timeOut
     * @return \Yew\Plugins\Actor\ActorIpcProxy|false
     */
    public static function getProxy(string $actorName, ?bool $oneWay = false, ?float $timeOut = 5)
    {
        try {
            return new ActorIpcProxy($actorName, $oneWay, $timeOut);
        } catch (ActorException $exception) {
            return false;
        }
    }

    

    /**
     * Enqueue a message into the mailbox, applying the configured overflow strategy.
     *
     * @param ActorMessage $message
     * @return bool True if enqueued, false if dropped (drop strategy) or rejected.
     */
    public function sendMessage(ActorMessage $message): bool
    {
        try {
            $pushed = $this->mailboxOverflowStrategy->enqueue(
                $this->channel,
                $message,
                $this->actorConfig->getMailboxPushTimeout()
            );
        } catch (ActorException $exception) {
            $this->error(sprintf("Actor %s mailbox rejected message: %s", $this->name, $exception->getMessage()));
            return false;
        }

        if ($pushed === false) {
            $this->warning(sprintf("Actor %s mailbox full, message dropped", $this->name));
        }

        return $pushed;
    }

    /**
     * Tick timer
     * @param int $msec
     * @param callable $callback
     * @param ...$params
     * @return false|int
     */
    public function tick(int $msec, callable $callback, ... $params)
    {
        $id = Timer::tick($msec, $callback, ...$params);
        $this->timerIds[$id] = $id;

        return $id;
    }

    /**
     * After timer
     * @param int $msec
     * @param callable $callback
     * @param ...$params
     * @return int
     */
    public function after(int $msec, callable $callback, ... $params): int
    {
        $id = Timer::after($msec, $callback, ...$params);
        $this->timerIds[$id] = $id;

        return $id;
    }

    /**
     * Clear timer
     * @param int $id
     * @return void
     */
    public function clearTimer(int $id)
    {
        Timer::clear($id);
        unset($this->timerIds[$id]);
    }

    /**
     * Clear all timer
     * @return void
     * @throws \Exception
     */
    public function clearAllTimer(): bool
    {
        if (!empty($this->timerIds)) {
            foreach ($this->timerIds as $timerId) {
                $this->clearTimer($timerId);
            }
            $this->debug(sprintf("Actor %s's all timer cleared", $this->getName()));
        }
        return true;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function saveContext(): void
    {
        Server::$instance->getLog()->debug(__METHOD__);

        $this->logHandle->log($this->data);

        return;
    }
}
