<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor\Log;

use Yew\Framework\Base\Component;

class Dispatcher extends Component
{

    /**
     * @var array
     */
    public array $targets = [];
    
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