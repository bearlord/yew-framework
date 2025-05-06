<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Exception;

use Throwable;
use Yew\Core\Plugins\Logger\GetLogger;

class AlertResponseException extends \Exception
{

    use GetLogger;

    /**
     * AlertResponseException constructor.
     * @param string|null $message
     * @param int $code
     * @param Throwable|null $previous
     * @throws \Exception
     */
    public function __construct(string $message = "", int $code = 500, ?Throwable $previous = null)
    {
        if (is_null($message)) {
            $message = "Internal server error. Please try again later";
        }
        $this->alert($message);
        $this->alert($this);

        return parent::__construct($message, $code, $previous);
    }
}