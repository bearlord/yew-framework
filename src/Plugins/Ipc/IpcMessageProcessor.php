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

    /**
     * Guards $sessions / $cacheMessages. handler() runs concurrently for every
     * actor in the process, so these shared maps need a coroutine mutex to stay
     * consistent under load (a corrupted PHP array would crash the worker).
     */
    private \Swoole\Coroutine\Mutex $sessionMutex;

    public function __construct()
    {
        parent::__construct(self::TYPE);
        $this->sessionMutex = new \Swoole\Coroutine\Mutex();
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

            $sessionId = $ipcCallData->getArguments()["sessionId"] ?? null;
            $args = $ipcCallData->getArguments();
            // Drop framework-internal keys (e.g. __traceId) so they are not
            // passed as named arguments to the business method.
            unset($args['__traceId']);

            // --- Critical section: only touch the shared $sessions / $cacheMessages
            //     maps here. The business call and the reply MUST stay OUTSIDE the
            //     lock, otherwise a method that re-enters IPC (or blocks on a
            //     Channel) would deadlock the whole cluster-state process and every
            //     caller would time out. ---
            $this->sessionMutex->lock();
            $locked = true;
            $run = false;
            try {
                $lockSessionId = $this->sessions[$sessionKey] ?? null;
                // A lock is a unix timestamp; expire it after a lease so a crashed
                // transaction can never block this actor's mailbox forever.
                if ($lockSessionId !== null && time() - $lockSessionId > 30) {
                    unset($this->sessions[$sessionKey]);
                    $lockSessionId = null;
                }

                // Transactional call: must match the live session id, otherwise it
                // is queued until the owning transaction completes.
                if ($lockSessionId === $sessionId) {
                    $_name = $ipcCallData->getName();
                    if ($_name === "__getSession") {
                        // Open a new transaction session; the timestamp doubles as
                        // the session token and the lease marker.
                        $this->sessions[$sessionKey] = time();
                        $locked = false;
                        $this->sessionMutex->unlock();
                        $this->reply($ipcCallData, $message, time(), null, null, null);
                        return true;
                    }
                    if ($_name === "__clearSession") {
                        $result = $this->sessions[$sessionKey] ?? null;
                        unset($this->sessions[$sessionKey]);
                        $locked = false;
                        $this->sessionMutex->unlock();
                        $this->reply($ipcCallData, $message, $result, null, null, null);
                        $this->drainCache($sessionKey);
                        return true;
                    }
                    // Default / business method: claim the slot (mark in-flight)
                    // without holding the mutex, then run + reply OUTSIDE the lock.
                    if ($sessionId != null) {
                        unset($args["sessionId"]);
                    }
                    $this->sessions[$sessionKey] = "RUNNING";
                    $run = true;
                } else {
                    // Transaction id mismatch (or a session is mid-flight): cache
                    // the message and process it once the owner finishes.
                    $this->cacheMessages[$sessionKey][] = $message;
                    $locked = false;
                    $this->sessionMutex->unlock();
                    return true;
                }
            } finally {
                if ($locked) {
                    $this->sessionMutex->unlock();
                }
            }

            // --- Outside the lock: execute the business method and reply. This is
            //     where the original code deadlocked the process. ---
            if ($run) {
                try {
                    $result = call_user_func_array([$handle, $ipcCallData->getName()], $args);
                } catch (\Throwable $e) {
                    $errorClass = get_class($e);
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();
                    $this->error($e);
                    // Drop the in-flight marker on error so it cannot leak.
                    $this->sessionMutex->lock();
                    try {
                        if (($this->sessions[$sessionKey] ?? null) === "RUNNING") {
                            unset($this->sessions[$sessionKey]);
                        }
                    } finally {
                        $this->sessionMutex->unlock();
                    }
                    $this->reply($ipcCallData, $message, null, $errorClass, $errorCode, $errorMessage);
                    return true;
                }

                $this->reply($ipcCallData, $message, $result, null, null, null);

                // Release the in-flight marker and drain any queued same-key calls.
                $this->sessionMutex->lock();
                try {
                    if (($this->sessions[$sessionKey] ?? null) === "RUNNING") {
                        unset($this->sessions[$sessionKey]);
                    }
                } finally {
                    $this->sessionMutex->unlock();
                }
                $this->drainCache($sessionKey);
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
    /**
     * Re-dispatch any messages that were queued while a session/transaction was
     * in flight for $sessionKey. Must be called OUTSIDE the session mutex, since
     * each re-dispatched message will acquire the mutex again in handler().
     */
    private function drainCache(string $sessionKey): void
    {
        $this->sessionMutex->lock();
        try {
            $cacheMessages = $this->cacheMessages[$sessionKey] ?? null;
            if (empty($cacheMessages)) {
                return;
            }
            unset($this->cacheMessages[$sessionKey]);
        } finally {
            $this->sessionMutex->unlock();
        }

        foreach ($cacheMessages as $cacheMessage) {
            goWithContext(function () use ($cacheMessage) {
                $this->handler($cacheMessage);
            });
        }
    }

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