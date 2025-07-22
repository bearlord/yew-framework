<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Amqp\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Yew\Plugins\AnnotationsScan\Annotation\Component;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Consumer extends Component
{
    /**
     * @var string
     */
    public string $exchange = "";

    /**
     * @var string
     */
    public string $routingKey = "";

    /**
     * @var string
     */
    public string $queue = "";

    /**
     * @var string
     */
    public string $name = "Consumer";

    /**
     * @var int
     */
    public int $nums = 1;

    /**
     * @var null|bool
     */
    public $enable;

    /**
     * @var int
     */
    public int $maxConsumption = 0;
}