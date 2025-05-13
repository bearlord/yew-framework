<?php
/**
 * ESD framework
 * @author tmtbe <565364226@qq.com>
 */

namespace Yew\Plugins\Autostart\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 * Class Scheduled
 */
class Autostart extends Annotation
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var int
     */
    public int $sort;

    /**
     * @var int
     */
    public int $delay;
}