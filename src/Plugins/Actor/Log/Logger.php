<?php

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


    public function log($message)
    {
        $time = microtime(true);

        $this->messages[] = [$message, $time];

        if ($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval) {
            $this->flush();
        }
    }


    public function flush(?bool $final = false)
    {
        $messages = $this->messages;

        $this->messages = [];

        if ($this->dispatcher instanceof Dispatcher) {
            $this->dispatcher->dispatch($messages, $final);
        }
    }


}