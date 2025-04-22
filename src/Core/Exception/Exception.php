<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Exception;

use Throwable;

class Exception extends \Exception
{

    protected bool $trace = true;

    protected int $time;

    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->time = (int)(microtime(true) * 1000 * 1000);
    }

    /**
     * @return bool
     */
    public function isTrace(): bool
    {
        return $this->trace;
    }

    /**
     * @param bool $trace
     */
    public function setTrace(bool $trace): void
    {
        $this->trace = $trace;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }
}