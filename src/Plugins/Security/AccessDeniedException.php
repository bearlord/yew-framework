<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Exception;

class AccessDeniedException extends Exception
{
    public function __construct($message = "No corresponding permissions", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
