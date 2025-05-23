<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;

use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;

/**
 * Injects line/file:class/function where the log message came from
 *
 * Warning: This only works if the handler processes the logs directly.
 * If you put the processor on a handler that is behind a FingersCrossedHandler
 * for example, the processor will only be called once the trigger level is reached,
 * and all the log records will have the same file/line/.. data from the call that
 * triggered the FingersCrossedHandler.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class GoIntrospectionProcessor implements ProcessorInterface
{
    private $level;

    private array $skipClassesPartials;

    private $skipStackFramesCount;

    private array $skipFunctions = [
        'call_user_func',
        'call_user_func_array'
    ];

    private array $ingoreFunctions = [
        'addRecord',
        'info',
        'log',
        'debug',
        'notice',
        'warn',
        'warning',
        'err',
        'error',
        'crit',
        'critical',
        'alert',
        'emerg',
        'emergency'
    ];

    public function __construct($level = Logger::DEBUG, array $skipClassesPartials = array(), $skipStackFramesCount = 0)
    {
        $this->level = Logger::toMonologLevel($level);
        $this->skipClassesPartials = array_merge(array('Yew\\Core\\Plugins\\Logger\\', 'Monolog\\'), $skipClassesPartials);
        $this->skipStackFramesCount = $skipStackFramesCount;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        // return if the level is not high enough
        if ($record['level'] < $this->level) {
            return $record;
        }

        /*
        * http://php.net/manual/en/function.debug-backtrace.php
        * As of 5.3.6, DEBUG_BACKTRACE_IGNORE_ARGS option was added.
        * Any version less than 5.3.6 must use the DEBUG_BACKTRACE_IGNORE_ARGS constant value '2'.
        */
        $trace = debug_backtrace((PHP_VERSION_ID < 50306) ? 2 : DEBUG_BACKTRACE_IGNORE_ARGS);

        // skip first since it's always the current method
        array_shift($trace);
        // the call_user_func call is also skipped
        array_shift($trace);

        $i = 0;

        while ($this->isTraceClassOrSkippedFunction($trace, $i)) {
            if (isset($trace[$i]['class'])) {
                if (in_array($trace[$i]['function'], $this->ingoreFunctions)) {
                    $i++;
                    continue;
                }
                foreach ($this->skipClassesPartials as $part) {
                    if (strpos($trace[$i]['class'], $part) !== false) {
                        $i++;
                        continue 2;
                    }
                }
            } elseif (in_array($trace[$i]['function'], $this->skipFunctions)) {
                $i++;
                continue;
            }

            break;
        }

        $i += $this->skipStackFramesCount;

        // we should have the call source now
        $record['extra'] = array_merge(
            $record['extra'],
            array(
                'file' => $trace[$i - 1]['file'] ?? null,
                'line' => $trace[$i - 1]['line'] ?? null,
                'class' => $trace[$i]['class'] ?? null,
                'function' => $trace[$i]['function'] ?? null,
            )
        );

        return $record;
    }

    private function isTraceClassOrSkippedFunction(array $trace, $index): bool
    {
        if (!isset($trace[$index])) {
            return false;
        }

        return isset($trace[$index]['class']) || in_array($trace[$index]['function'], $this->skipFunctions);
    }
}
