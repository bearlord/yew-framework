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
use Yew\Plugins\Actor\Log\LogFactory;
use Yew\Plugins\Actor\Multicast\MulticastConfig;
use Yew\Plugins\Actor\Multicast\Channel as MulticastChannel;
use Yew\Plugins\Ipc\GetIpc;
use Yew\Coroutine\Server\Server;
use Yew\Yew;
use Swoole\Timer;

abstract class Actor
{
    use GetLogger;

    use GetIpc;

    /**
     * @var MulticastConfig
     */
    protected $multicastConfig;

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
    protected array $data;

    /**
     * @var array timer ids
     */
    protected array $timerIds = [];

    /**
     * @var Log\Logger
     */
    protected $logHandle;

    /**
     * @param string|null $name
     * @param bool $isCreated
     * @throws \DI\DependencyException
     */
    final public function __construct(?string $name = '', bool $isCreated = false)
    {
        $this->name = $name;

        Server::$instance->getContainer()->injectOn($this);
        if ($isCreated) {
            ActorManager::getInstance()->addActor($this);
        }

        $this->channel = DIGet(Channel::class, [$this->actorConfig->getActorMailboxCapacity()]);

        //Loop process the information in the mailbox
        goWithContext(function () use ($name) {
            while (true) {
                $message = $this->channel->pop();
                $this->onHandleMessage($message);
            }
        });

        $this->logHandle = LogFactory::create($name);

        $this->tick(10 * 1000, [$this, 'saveContext']);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
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
     * @throws \Exception
     */
    public function destroy()
    {
        $this->clearAllTimer();
        ActorManager::getInstance()->removeActor($this);
    }

    /**
     * Get proxy
     * @param string $actorName
     * @param bool $oneway
     * @param float|null $timeOut
     * @return \Yew\Plugins\Actor\ActorIpcProxy|false
     */
    public static function getProxy(string $actorName, ?bool $oneway = false, ?float $timeOut = 5)
    {
        try {
            return new ActorIpcProxy($actorName, $oneway, $timeOut);
        } catch (ActorException $exception) {
            return false;
        }
    }

    /**
     * Create
     * @param string $actionClass
     * @param string $actorName
     * @param null $data
     * @param bool $waitCreate
     * @param float|null $timeOut
     * @return \Yew\Plugins\Actor\ActorIpcProxy|false|void
     * @throws \Yew\Plugins\Actor\ActorException
     */
    public static function create(string $actionClass, string $actorName, $data = null, ?bool $waitCreate = true, ?float $timeOut = 5)
    {
        if ($waitCreate && ActorManager::getInstance()->hasActor($actorName)) {
            return new ActorIpcProxy($actorName, false, $timeOut);
        }

        $processes = Server::$instance->getProcessManager()->getProcessGroup(ActorConfig::GROUP_NAME);

        $nowProcess = ActorManager::getInstance()->getAtomic()->add();
        $index = $nowProcess % count($processes->getProcesses());

        Server::$instance->getEventDispatcher()->dispatchProcessEvent(new ActorCreateEvent(
            ActorCreateEvent::ActorCreateEvent,
            [
                $actionClass, $actorName, $data, true
            ]), $processes->getProcesses()[$index]);

        if ($waitCreate) {
            $call = Server::$instance->getEventDispatcher()->listen(ActorCreateEvent::ActorCreateReadyEvent . ":" . $actorName, null, true);
            $result = $call->wait($timeOut);
            if ($result == null) {
                return false;
            }

            return new ActorIpcProxy($actorName, false, $timeOut);
        }
    }

    /**
     * Proxy receive a message, throw it in the mailbox
     * @param ActorMessage $message
     */
    public function sendMessage(ActorMessage $message)
    {
        $this->channel->push($message);
    }

    /**
     * Start transaction
     * @param callable $call
     */
    public function startTransaction(callable $call)
    {

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


    /**
     * @return MulticastConfig|mixed
     * @throws \Exception
     */
    protected function getMulticastConfig(): MulticastConfig
    {
        if ($this->multicastConfig == null) {
            $this->multicastConfig = DIGet(MulticastConfig::class);
        }

        return $this->multicastConfig;
    }


    /**
     * @param string $channel
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function subscribe(string $channel)
    {
        $actor = $this->getName();

        /** @var \Yew\Plugins\Actor\Multicast\Channel $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), MulticastChannel::class, true);

        $ipcProxy->subscribe($channel, $actor);
    }

    /**
     * Unsubscribe
     *
     * @param string $channel
     * @throws \Exception
     */
    public function unsubscribe(string $channel)
    {
        $actor = $this->getName();

        /** @var \Yew\Plugins\Actor\Multicast\Channel $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), MulticastChannel::class, true);
        $ipcProxy->unsubscribe($channel, $actor);
    }

    /**
     * Unsubscribe all
     * @return void
     * @throws \Exception
     */
    public function unsubscribeAll(): void
    {
        $actor = $this->getName();

        /** @var \Yew\Plugins\Actor\Multicast\Channel $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), MulticastChannel::class, true);
        $ipcProxy->unsubscribeAll($actor);
    }

    /**
     * @param string $channel
     * @param string $message
     * @param array|null $excludeActorList
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function publish(string $channel, string $message, ?array $excludeActorList = []): void
    {
        $from = $this->getName();

        if (empty($excludeActorList)) {
            $excludeActorList = [$from];
        }

        /** @var \Yew\Plugins\Actor\Multicast\Channel $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), MulticastChannel::class, true);

        $ipcProxy->publish($channel, $message, $excludeActorList, $from);
    }
    
    /**
     * @param string $channel
     * @param string $message
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function publishTo(string $channel, string $message): void
    {
        $from = $this->getName();

        $excludeActorList = [$from];

        /** @var \Yew\Plugins\Actor\Multicast\Channel $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), MulticastChannel::class, true);
        $ipcProxy->publish($channel, $message, $excludeActorList, $from);
    }

    /**
     * @param string $channel
     * @param string $message
     * @return void
     * @throws \Yew\Plugins\Ipc\IpcException
     */
    public function publishIn(string $channel, string $message)
    {
        $from = $this->getName();

        $excludeActorList = [];

        /** @var \Yew\Plugins\Actor\Multicast\Channel $ipcProxy */
        $ipcProxy = $this->callProcessName($this->getMulticastConfig()->getProcessName(), MulticastChannel::class, true);
        $ipcProxy->publish($channel, $message, $excludeActorList, $from);
    }
}
