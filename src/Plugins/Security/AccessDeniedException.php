<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Core\Exception\Exception;

class AccessDeniedException extends Exception
{
    public function __construct()
    {
        parent::__construct("No corresponding permissions", 0, null);
        $this->setTrace(false);
    }
}