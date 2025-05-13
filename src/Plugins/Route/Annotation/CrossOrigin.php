<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class CrossOrigin extends Annotation
{

    /**
     * @var array allow origins
     */
    public array $allowedOrigins = ["*"];

    /**
     * @var array allowed methods
     */
    public array $allowedMethods = ["*"];

    /**
     * @var array allow headers
     */
    public array $allowedHeaders = ["*"];

    /**
     * @var array exposed headers
     */
    public array $exposedHeaders = ["*"];

    /**
     * @var bool allow credentials
     */
    public bool $allowCredentials = false;

    /**
     * @var int max age
     */
    public int $maxAge = 3628800;

}