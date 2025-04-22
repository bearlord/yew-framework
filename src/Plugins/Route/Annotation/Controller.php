<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Annotation;

use Yew\Plugins\AnnotationsScan\Annotation\Component;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Controller extends Component
{
    /**
     * Route prefix
     * @var string
     */
    public $value = "";

    /**
     * Default method
     * @var string
     */
    public string $defaultMethod;

    /**
     * Port access type, http, ws, tcp, udp, unlimited if empty array
     * @var array
     */
    public array $portTypes = [];

    /**
     * Port name, unlimited if empty array
     * @var array
     */
    public array $portNames = [];
}