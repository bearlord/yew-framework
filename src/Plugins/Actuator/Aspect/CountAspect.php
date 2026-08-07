<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actuator\Aspect;

use Yew\Core\DI\DI;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Route\Aspect\RouteAspect;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Around;

/**
 * Increments per-route request counters on every HTTP request.
 */
class CountAspect extends OrderAspect
{
    use GetLogger;

    protected ?Table $table = null;

    public function __construct()
    {
        $this->atBefore(RouteAspect::class);
        try {
            $this->table = DI::getInstance()->get("RouteCountTable");
        } catch (\Throwable $e) {
            $this->table = null;
        }
    }

    public function getName(): string
    {
        return "ActuatorCountAspect";
    }

    /**
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onHttpRequest(*))")
     */
    protected function aroundHttpRequest(MethodInvocation $invocation)
    {
        if ($this->table !== null) {
            /** @var Request $request */
            /** @var Response $response */
            list($request, $response) = $invocation->getArguments();
            $path = $request->getUri()->getPath();
            $this->table->incr($path, "num_60");
            $this->table->incr($path, "num_3600");
            $this->table->incr($path, "num_86400");
        }

        return $invocation->proceed();
    }
}
