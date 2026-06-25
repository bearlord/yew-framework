<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Log;

use Yew\Framework\Base\Component;

class Logger extends Component
{

    /**
     * @var array
     */
    public array $messages = [];

    /**
     * @var int 
     */
    public int $flushInterval = 1;

    /**
     * @var Dispatcher the message dispatcher
     */
    public $dispatcher;

	/**
	 * @param $message
	 * @return void
	 */
    public function log($message)
    {
        $time = microtime(true);

        $this->messages[] = [$message, $time];

        if ($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval) {
            $this->flush();
        }
    }

	/**
	 * @param bool|null $final
	 * @return void
	 */
    public function flush(?bool $final = false)
    {
        $messages = $this->messages;

        $this->messages = [];

        if ($this->dispatcher instanceof Dispatcher) {
            $this->dispatcher->dispatch($messages, $final);
        }
    }


}