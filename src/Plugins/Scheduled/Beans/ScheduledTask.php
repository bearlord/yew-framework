<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled\Beans;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Plugins\Scheduled\Cron\CronExpression;

class ScheduledTask extends BaseConfig
{
    const KEY = "scheduled.task";

    const PROCESS_GROUP_ALL = "all";

    const GROUP_NAME = "ScheduledGroup";

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var string
     */
    protected string $expression;

    /**
     * @var string
     */
    protected string $className;

    /**
     * @var string
     */
    protected string $functionName;

    /**
     * @var string
     */
    protected string $processGroup = ScheduledTask::GROUP_NAME;

    /**
     * @var CronExpression
     */
    private CronExpression $cron;

    /**
     * ScheduledTask constructor.
     * @param string $name
     * @param string $expression
     * @param string $className
     * @param string $functionName
     * @param string $processGroup
     */
    public function __construct(
        string $name,
        string $expression,
        string $className,
        string $functionName,
        string $processGroup = ScheduledTask::GROUP_NAME)
    {
        parent::__construct(self::KEY);
        $this->name = $name;
        $this->expression = $expression;
        $this->className = $className;
        $this->functionName = $functionName;
        $this->processGroup = $processGroup;
        if ($expression != null) {
            $this->cron = CronExpression::factory($expression);
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * @param string $expression
     */
    public function setExpression(string $expression): void
    {
        $this->expression = $expression;
        $this->cron = CronExpression::factory($expression);
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param string $className
     */
    public function setClassName(string $className): void
    {
        $this->className = $className;
    }

    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    /**
     * @param string $functionName
     */
    public function setFunctionName(string $functionName): void
    {
        $this->functionName = $functionName;
    }

    /**
     * @return string
     */
    public function getProcessGroup(): string
    {
        return $this->processGroup;
    }

    /**
     * @param string $processGroup
     */
    public function setProcessGroup(string $processGroup): void
    {
        $this->processGroup = $processGroup;
    }

    /**
     * @return CronExpression
     */
    public function getCron(): CronExpression
    {
        return $this->cron;
    }
}