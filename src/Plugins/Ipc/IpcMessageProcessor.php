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
     * Safe telemetry logging. The GetLogger trait routes through
     * Server::$instance->getLog(), which can be null/uninitialized in some
     * processes (e.g. cluster-state). Never let a debug log crash the real
     * message-handling path.
     */
    private function telemetry(string $msg): void
    {
        try {
            $logger = Server::$instance->getLog();
            if ($logger !== null) {
                $logger->log(\Monolog\Logger::DEBUG, $msg, []);
            }
        } catch (\Throwable $e) {
            // swallow: telemetry must never affect business processing
        }
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
            $token = $ipcCallData->getToken();
            $method = $ipcCallData->getName();
            $actorName = $message instanceof ActorIpcCallMessage ? $ipcCallData->getActorName() : null;
            $t0 = microtime(true);

            // ---- Telemetry: worker asked, actor process received ----
            // Helps distinguish "worker failed to send" (no such log on the actor
            // side) from "actor process slow to process / reply" (large delta
            // between this and the reply below). Keyed by token so the caller's
            // logs can be correlated.
            $this->telemetry(sprintf(
                "[ipc-telemetry] RECV token=%d method=%s actor=%s",
                $token, $method, $actorName ?? 'service'
            ));

            // Actor proxies carry the actor name in a dedicated field of the
            // ActorIpcCallMessage subtype, so the class name stays a clean DI
            // identifier. Resolve actor handles through ActorManager::getActor().
            $handle = null;
            $className = $ipcCallData->getClassName();
            if ($message instanceof ActorIpcCallMessage) {
                $tGetActor = microtime(true);
                $actor = \Yew\Plugins\Actor\ActorManager::getInstance()->getActor($ipcCallData->getActorName());
                $getActorMs = (microtime(true) - $tGetActor) * 1000;
                if ($getActorMs > 1) {
                    $this->telemetry(sprintf(
                        "[ipc-telemetry] GETACTOR_SLOW token=%d actor=%s %.2fms",
                        $token, $ipcCallData->getActorName(), $getActorMs
                    ));
                }
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
                        $_method = $ipcCallData->getName();
                        try {
                            $tExec = microtime(true);
                            $result = call_user_func_array([$handle, $_method], $args);
                            $execMs = (microtime(true) - $tExec) * 1000;
                            if ($execMs > 5) {
                                $this->telemetry(sprintf(
                                    "[ipc-telemetry] EXEC_SLOW token=%d method=%s actor=%s %.2fms",
                                    $token, $method, $actorName ?? 'service', $execMs
                                ));
                            }
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
                $queueLen = count($this->cacheMessages[$sessionKey] ?? []);
                $this->cacheMessages[$sessionKey][] = $message;
                // A non-matching session id means the actor is busy processing a
                // prior message and this one is being queued in $cacheMessages.
                // A growing queue here is the direct signal of "actor process
                // cannot keep up" (the mailbox backlog), which is what surfaces
                // as an IPC timeout on the caller side.
                $this->telemetry(sprintf(
                    "[ipc-telemetry] QUEUED token=%d actor=%s queueLen=%d",
                    $token, $sessionKey, $queueLen + 1
                ));

                return true;
            }

            $this->reply($ipcCallData, $message, $result, $errorClass, $errorCode, $errorMessage);

            $totalMs = (microtime(true) - $t0) * 1000;
            $this->telemetry(sprintf(
                "[ipc-telemetry] DONE token=%d method=%s actor=%s %.2fms",
                $token, $method, $actorName ?? 'service', $totalMs
            ));

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
