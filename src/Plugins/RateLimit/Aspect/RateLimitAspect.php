<?php

namespace Yew\Core\Plugins\RateLimit\Aspect;

use Yew\Plugins\Aop\OrderAspect;

class RateLimitAspect extends OrderAspect
{

    public function getName(): string
    {
        return 'RateLimit';
    }


}