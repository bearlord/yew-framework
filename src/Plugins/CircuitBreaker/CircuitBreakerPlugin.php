<?php

namespace Yew\Plugins\CircuitBreaker;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\CircuitBreaker\Aspect\CircuitBreakerAspect;
use Yew\Plugins\Pack\PackPlugin;
use Yew\Plugins\Redis\RedisPlugin;
use Yew\Plugins\Route\RoutePlugin;

class CircuitBreakerPlugin  extends AbstractPlugin
{

    public function __construct()
    {
        parent::__construct();

        $this->atAfter(RedisPlugin::class);
        $this->atAfter(PackPlugin::class);
        $this->atAfter(RoutePlugin::class);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "CircuitBreaker";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function init(Context $context)
    {
        $aopConfig = DIget(AopConfig::class);

        $circuitBreakerAspect = new CircuitBreakerAspect();

        $aopConfig->addAspect($circuitBreakerAspect);
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
        // TODO: Implement beforeServerStart() method.
    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}