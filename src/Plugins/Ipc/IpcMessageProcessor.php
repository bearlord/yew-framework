<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

use Yew\Core\Message\Message;
use Yew\Core\Message\MessageProcessor;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\ActorIpcCallMessage;

class IpcMessageProcessor extends MessageProcessor
{
    use GetLogger;

    const TYPE = "@ipc";

    /**
     * @var array
     */
    protected array $sessions = [];

    /**
     * @var Message[]
     */
    protected array $cacheMessages = [];

    public function __construct()
    {
        parent::__construct(self::TYPE);
    }

    /**
     * @param Message $message
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function handler(Message $message): bool
    {
        if ($message instanceof IpcCallMessage) {
            $ipcCallData = $message->getProcessIpcCallData();

            // Actor proxies carry the actor name in a dedicated field of the
            // ActorIpcCallMessage subtype, so the class name stays a clean DI
            // identifier. Resolve actor handles through ActorManager::getActor().
            $handle = null;
            $className = $ipcCallData->getClassName();
            if ($message instanceof ActorIpcCallMessage) {
                $actor = \Yew\Plugins\Actor\ActorManager::getInstance()->getActor($ipcCallData->getActorName());
                if ($actor instanceof \Yew\Plugins\Actor\Actor) {
                    $handle = $actor;
                }
            }
            try {
                if ($handle === null) {
                    $handle = Server::$instance->getContainer()->get($className);
                }
            } catch (\Throwable $e) {
                // Resolution failure (e.g. actor not owned by this process, or the
                // class is not a registered DI service) must still produce a reply
                // so the caller does not hang on a silent timeout.
                $errorClass = get_class($e);
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                $this->error($e);
                $this->reply($ipcCallData, $message, null, $errorClass, $errorCode, $errorMessage);
                return true;
            }
            $result = null;
            $errorClass = null;
            $errorCode = null;
            $errorMessage = null;

            // Session lock key: actor messages are keyed by the actor name so each
            // actor instance keeps its own transaction lock (equivalent to the old
            // "ClassName:actorName" key). Plain service classes keep the class name
            // as the lock key, matching the per-DI-singleton transaction semantics.
            $sessionKey = $message instanceof ActorIpcCallMessage
                ? $ipcCallData->getActorName()
                : $ipcCallData->getClassName();

            $lockSessionId = $this->sessions[$sessionKey] ?? null;
            $sessionId = $ipcCallData->getArguments()["sessionId"] ?? null;
            $args = $ipcCallData->getArguments();
            // Strip framework-internal metadata keys that travel inside the
            // argument bag (e.g. the distributed-tracing id injected by
            // ActorIpcProxy::tell/ask). They are not business method parameters,
            // and call_user_func_array would otherwise expand them as named
            // arguments and fatal with "Unknown named parameter $__traceId".
            unset($args['__traceId']);

            if ($lockSessionId === $sessionId) {
                if ($sessionId != null) {
                    unset($args["sessionId"]);
                }

                $_name = $ipcCallData->getName();

                switch ($_name) {
                    case "__getSession":
                        $result = time();
                        $this->sessions[$_name] = $result;
                        break;

                    case "__clearSession":
                        $result = $this->sessions[$_name] ?? null;
                        unset($this->sessions[$_name]);
                        break;

                    default:
                        try {
                            $result = call_user_func_array([$handle, $ipcCallData->getName()], $args);
                        } catch (\Throwable $e) {
                            $errorClass = get_class($e);
                            $errorCode = $e->getCode();
                            $errorMessage = $e->getMessage();
                            $this->error($e);
                        }
                        break;

                }
            } else {
                //The transaction id does not match and cache the message
                $this->cacheMessages[$sessionKey][] = $message;

                return true;
            }

            $this->reply($ipcCallData, $message, $result, $errorClass, $errorCode, $errorMessage);

            //Processing cache
            if (!isset($this->sessions[$sessionKey])) {
                $cacheMessages = $this->cacheMessages[$sessionKey] ?? null;
                if (!empty($cacheMessages)) {
                    foreach ($cacheMessages as $cacheMessage) {
                        goWithContext(function () use ($cacheMessage) {
                            $this->handler($cacheMessage);
                        });
                    }
                }
            }

            return true;
        } else if ($message instanceof IpcResultMessage) {
            $ipcResultData = $message->getIpcResultData();
            IpcManager::callChannel($ipcResultData->getToken(), $ipcResultData);

            return true;
        }

        return false;
    }

    /**
     * Send the IPC result back to the caller process.
     *
     * Always replies (unless the call was one-way) so the caller never hangs on
     * a silent timeout when the handler produced an error or could not resolve
     * a handle.
     *
     * @param IpcCallData $ipcCallData
     * @param Message     $message
     * @param mixed       $result
     * @param string|null $errorClass
     * @param int|null    $errorCode
     * @param string|null $errorMessage
     */
    private function reply(IpcCallData $ipcCallData, Message $message, $result, ?string $errorClass, ?int $errorCode, ?string $errorMessage): void
    {
        if ($ipcCallData->isOneway()) {
            return;
        }

        Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage(
            new IpcResultMessage($ipcCallData->getToken(), $result, $errorClass, $errorCode, $errorMessage),
            Server::$instance->getProcessManager()->getProcessFromId($message->getFromProcessId())
        );
    }
}