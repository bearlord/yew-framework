<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;

use Yew\Core\Server\Server;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Swoole\Coroutine;

class GoSwooleProcessor implements ProcessorInterface
{
    private $level;

    private ?array $skipClassesPartials;

    private ?int $skipStackFramesCount;

    private ?bool $color;

    /**
     * GoSwooleProcessor constructor.
     * @param bool|null $color
     * @param int|null $level
     * @param array|null $skipClassesPartials
     * @param int|null $skipStackFramesCount
     */
    public function __construct(?bool $color = true, ?int $level = Logger::DEBUG, ?array $skipClassesPartials = [], ?int $skipStackFramesCount = 0)
    {
        $this->level = Logger::toMonologLevel($level);
        $this->skipClassesPartials = array_merge(array('Monolog\\'), $skipClassesPartials);
        $this->skipStackFramesCount = $skipStackFramesCount;
        $this->color = $color;
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        // return if the level is not high enough
        if ($record['level'] < $this->level) {
            return $record;
        }
        $process = Server::$instance->getProcessManager()->getCurrentProcess();
        if ($process != null) {
            // we should have the call source now
            $record['extra'] = array_merge(
                $record['extra'],
                array(
                    'processId' => $process->getProcessId(),
                    'processName' => $process->getProcessName(),
                    'processGroup' => $process->getGroupName(),
                    'cid' => Coroutine::getCid()
                )
            );
            $record['user'] = LoggerExtra::get()->getContext();
        }
        $this->setLength($record);
        return $record;
    }

    /**
     * Set length
     *
     * @param $record
     */
    private function setLength(&$record)
    {
        $record['level_name'] = $this->handleLevelName($record['level'], $record['level_name']);
        $record['extra']['class_and_func'] = $this->handleClassName($record['extra']['class'] ?? null, $record['extra']['function'] ?? null);
        $record['extra']['about_process'] = $this->handleProcess($record['extra']['processGroup'] ?? null, $record['extra']['processName'] ?? null, $record['extra']['cid'] ?? null);
    }

    /**
     * Handle level name
     *
     * @param $level
     * @param $levelName
     * @return string
     */
    private function handleLevelName($level, $levelName): string
    {
        $levelName = sprintf('%-7s', $levelName);
        if ($this->color) {
            if ($level >= Logger::ERROR) {
                $levelName = "\e[31m" . $levelName . "\e[0m";
            } elseif ($level >= Logger::WARNING) {
                $levelName = "\e[33m" . $levelName . "\e[0m";
            } elseif ($level >= Logger::INFO) {
                $levelName = "\e[32m" . $levelName . "\e[0m";
            } else {
                $levelName = "\e[34m" . $levelName . "\e[0m";
            }
        }
        return $levelName;
    }

    /**
     * Handle process
     *
     * @param $processGroup
     * @param $processName
     * @param $cid
     * @return string
     */
    private function handleProcess($processGroup, $processName, $cid): string
    {
        $processName = sprintf('%10s', $processName);
        $result = sprintf("[%15s|%15s|%8s]", $processGroup, $processName, $cid);
        if ($this->color) {
            return "\e[35m" . $result . "\e[0m";
        } else {
            return $result;
        }
    }

    private int $classNameMax = 50;

    /**
     * Handle class name
     *
     * @param $class
     * @param $func
     * @return string
     */
    private function handleClassName($class, $func): string
    {
        $maxLength = 25;
        if (!empty($class) && strlen($class) > $maxLength) {
            $count = strlen($class);
            $array = explode("\\", $class);
            foreach ($array as &$one) {
                $countOne = strlen($one);
                $one = strtolower($one[0]);
                $count = $count - $countOne + 1;
                if ($count <= $maxLength) break;
            }
            $class = implode(".", $array);
        }

        $class = str_replace("\\", ".", $class);
        if (stristr($func, "{closure}")) {
            $func = "{closure}";
        }
        $result = $class . "::" . $func;
        $this->classNameMax = max($this->classNameMax, strlen($result));
        $result = sprintf("%-{$this->classNameMax}s", $result);
        if ($this->color) {
            return "\e[36m" . $result . "\e[0m";
        } else {
            return $result;
        }
    }

    /**
     * @return bool
     */
    public function isColor(): bool
    {
        return $this->color;
    }

    /**
     * @param bool $color
     */
    public function setColor(bool $color): void
    {
        $this->color = $color;
    }
}
