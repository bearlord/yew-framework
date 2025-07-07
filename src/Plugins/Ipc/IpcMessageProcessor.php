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
            $handle = Server::$instance->getContainer()->get($ipcCallData->getClassName());
            $result = null;
            $errorClass = null;
            $errorCode = null;
            $errorMessage = null;

            $lockSessionId = $this->sessions[$ipcCallData->getClassName()] ?? null;
            $sessionId = $ipcCallData->getArguments()['sessionId'] ?? null;
            $args = $ipcCallData->getArguments();

            if ($lockSessionId === $sessionId) {
                if ($sessionId != null) {
                    unset($args['sessionId']);
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
                $this->cacheMessages[$ipcCallData->getClassName()][] = $message;

                return true;
            }

            if (!$ipcCallData->isOneway()) {
                Server::$instance->getProcessManager()->getCurrentProcess()->sendMessage(
                    new IpcResultMessage($ipcCallData->getToken(), $result, $errorClass, $errorCode, $errorMessage),
                    Server::$instance->getProcessManager()->getProcessFromId($message->getFromProcessId())
                );
            }
            
            //Processing cache
            if (!isset($this->sessions[$ipcCallData->getClassName()])) {
                $cacheMessages = $this->cacheMessages[$ipcCallData->getClassName()] ?? null;
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
}