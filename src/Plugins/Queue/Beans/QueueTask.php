<?php
/**
 * ESD framework
 * @author tmtbe <896369042@qq.com>
 */

namespace Yew\Plugins\Queue\Beans;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Plugins\Scheduled\Cron\CronExpression;

class QueueTask extends BaseConfig
{
    const KEY = "queue.task";

    const GROUP_NAME = "QueueGroup";

    /**
     * @var string
     */
    protected $processGroup = QueueTask::GROUP_NAME;

    /**
     * QueueTask constructor.
     * @param string $processGroup
     */
    public function __construct(string $processGroup = QueueTask::GROUP_NAME)
    {
        parent::__construct(self::KEY);
        $this->processGroup = $processGroup;
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
}