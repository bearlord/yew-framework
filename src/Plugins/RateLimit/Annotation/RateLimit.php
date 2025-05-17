<?php

namespace Yew\Plugins\RateLimit\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class RateLimit extends Annotation
{

    public ?int $create = null;

    public ?int $consume = null;

    public ?int $capacity = null;

    public $limitCallback = null;

    public $key = null;

    public ?int $waitTimeout = null;

    public ?string $ip = null;
}