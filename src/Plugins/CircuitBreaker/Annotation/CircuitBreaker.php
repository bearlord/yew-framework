<?php

namespace Yew\Core\Plugins\CircuitBreaker\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class CircuitBreaker extends Annotation
{
    public string $handler = TimeoutHandler::class;
    public $fallback = [];
    public float $duration = 10.0;
    public int $successCounter = 10;
    public int $failCounter = 10;
    public array $options = [];
}