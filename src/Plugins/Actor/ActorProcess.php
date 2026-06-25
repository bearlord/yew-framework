<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

use Carbon\Carbon;
use Yew\Core\Message\Message;
use Yew\Core\Server\Process\Process;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Actor\Event\ActorCreateEvent;
use Yew\Plugins\Actor\Event\ActorSaveEvent;
use Yew\Yew;

class ActorProcess extends Process
{

	/**
	 * @return void
	 */
	public function init()
	{

	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function onProcessStart()
	{
		$call = $this->eventDispatcher->listen(ActorCreateEvent::ActorCreateEvent);
		$call->call(function (ActorCreateEvent $event) {
			$_data = $event->getData();

			$class = $_data[0];
			$name = $_data[1];
			$data = $_data[2] ?? null;
			$isCreated = $_data[3] ?? false;
			$actor = new $class($name, $isCreated);

			if ($actor instanceof Actor) {
				$actor->initData($data);
			} else {
				throw new ActorException(sprintf("%s is not a actor", $class));
			}

			$this->eventDispatcher->dispatchProcessEvent(new ActorCreateEvent(ActorCreateEvent::ActorCreateReadyEvent . ":" . $actor->getName(), null),
				Server::$instance->getProcessManager()->getProcessFromId($event->getProcessId())
			);

			//Dispatch ActorSaveEvent to actor-cache process, do not need reply
			/*
			Server::$instance->getEventDispatcher()->dispatchProcessEvent(new ActorSaveEvent(
				ActorSaveEvent::ActorSaveEvent,
				[
					$class, $name, $data,
				]), Server::$instance->getProcessManager()->getProcessFromName(ActorCacheProcess::PROCESS_NAME));
			*/

		});

		Server::$instance->getProcessTable()->set($this->processName, [
			"process_name" => $this->processName,
			"status" => 0,
			"init_time" => (Carbon::now())->format("Y-m-d H:i:s.u"),
			"ready_time" => null,
			"last_exit_time" => null
		]);
	}

	/**
	 * @return void
	 */
	public function onProcessStop()
	{

	}

	/**
	 * @param Message $message
	 * @param Process $fromProcess
	 * @return void
	 */
	public function onPipeMessage(Message $message, Process $fromProcess)
	{

	}
}
