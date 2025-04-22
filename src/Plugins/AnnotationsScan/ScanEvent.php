<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AnnotationsScan;

use Yew\Core\Plugins\Event\Event;

/**
 * Class ScanEvent
 * @package Yew\Plugins\AnnotationsScan
 */
class ScanEvent extends Event
{
    /**
     * Type
     */
    const type = "ScanEvent";

    /**
     * ScanEvent constructor.
     */
    public function __construct()
    {
        parent::__construct(self::type, "");
    }
}