<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Scheduled\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Yew\Plugins\Scheduled\Beans\ScheduledTask;

/**
 * @Annotation
 * @Target("METHOD")
 * Class Scheduled
 * @package Yew\Plugins\Scheduled\Annotation
 */
class Scheduled extends Annotation
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $cron;

    /**
     * @var string
     */
    public string $processGroup = ScheduledTask::GROUP_NAME;
}