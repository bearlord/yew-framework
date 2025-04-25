<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Exception;

use Throwable;

/**
 * Class ParamException
 * @package ESD\Core
 */
class ParamException extends Exception
{
    /**
     * ParamException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setTrace(false);
    }
}
