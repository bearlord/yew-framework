<?php

namespace Yew\Plugins\CircuitBreaker\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Yew\Plugins\CircuitBreaker\Handler\TimeoutHandler;

/**
 * @Annotation
 * @Target("METHOD")
 */
class CircuitBreaker extends Annotation
{
    /**
     * @var string
     */
    public string $handler = TimeoutHandler::class;

    /**
     * @var array
     */
    public $fallback = [];

    /**
     * @var float
     */
    public float $duration = 10.0;

    /**
     * @var int
     */
    public int $successCounter = 10;

    /**
     * @var int
     */
    public int $failCounter = 10;

    /**
     * @var array 
     */
    public array $options = [];
}