<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace  Yew\Plugins\Amqp\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Yew\Plugins\AnnotationsScan\Annotation\Component;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Producer extends Component
{
    /**
     * @var string
     */
    public string $exchange = "";

    /**
     * @var string
     */
    public string $routingKey = "";
}
