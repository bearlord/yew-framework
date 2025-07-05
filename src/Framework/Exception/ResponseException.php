<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Exception;

use Yew\Core\Plugins\Logger\GetLogger;

class ResponseException extends \Exception
{

    use GetLogger;

    /**
     * ResponseException constructor.
     * @param null $message
     * @param int $code
     * @param \Throwable|null $previous
     * @throws \Exception
     */
    function __construct($message = null, $code = 200, \Throwable $previous = null)
    {
        if (is_null($message)) {
            $message = "The request failed. Please try again later";
        }
        $this->warn($message);

        return parent::__construct($message, $code, $previous);
    }
}