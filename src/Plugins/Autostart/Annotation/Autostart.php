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
 * @package Yew\Plugins\Autostart\Annotation
 */
class Autostart extends Annotation
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $sort;

    /**
     * @var int
     */
    public $delay;
}