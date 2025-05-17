<?php

namespace Yew\Plugins\RateLimit;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Plugins\Aop\AopConfig;
use Yew\Plugins\Pack\PackPlugin;
use Yew\Plugins\RateLimit\Aspect\RateLimitAspect;
use Yew\Plugins\Redis\RedisPlugin;
use Yew\Plugins\Route\RoutePlugin;

class RateLimitPlugin  extends AbstractPlugin
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
        return "RateLimit";
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function init(Context $context)
    {
        $aopConfig = DIget(AopConfig::class);

        $routeLimitAspect = new RateLimitAspect();

        $aopConfig->addAspect($routeLimitAspect);
    }



    public function beforeServerStart(Context $context)
    {
        // TODO: Implement beforeServerStart() method.
    }

    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}