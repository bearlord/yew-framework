<?php

namespace Yew\Plugins\Actor\Log;

use Yew\Framework\Base\Component;

class Dispatcher extends Component
{

    /**
     * @var array
     */
    public $targets = [];

    private $_logger;

    /**
     * @param $messages
     * @param $final
     * @return void
     */
    public function dispatch($messages, $final)
    {
        /** @var Target $target */
        foreach ($this->targets as $target) {
            if ($target->enabled) {
                try {
                    $target->collect($messages, $final);
                } catch (\Exception $e) {
                    //do nothing
                }
            }
        }
    }


}