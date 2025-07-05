<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace ESD\Plugins\Actuator\Aspect;

use Yew\Core\DI\DI;
use Yew\Core\Memory\CrossProcess\Table;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Plugins\Aop\OrderAspect;
use Yew\Plugins\Route\Aspect\RouteAspect;
use Yew\Coroutine\Server\Server;
use Yew\Goaop\Aop\Intercept\MethodInvocation;
use Yew\Goaop\Lang\Annotation\Around;


class CountAspect extends OrderAspect
{
    use GetLogger;

    /**
     * @var Table
     */
    protected Table $table;

    public function __construct()
    {
        $this->atBefore(RouteAspect::class);

        $this->table = DI::getInstance()->get('RouteCountTable');
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "ActuatorCountAspect";
    }


    /**
     * @Around("within(Yew\Core\Server\Port\IServerPort+) && execution(public **->onHttpRequest(*))")
     * @param MethodInvocation $invocation
     * @return mixed|void
     * @throws \Exception
     */
    protected function aroundHttpRequest(MethodInvocation $invocation)
    {
        /**
         * @var $response Response
         * @var $request Request
         */
        list($request, $response) = $invocation->getArguments();
        $path = $request->getUri()->getPath();

        $this->table->incr($path,'num_60');
        $this->table->incr($path,'num_3600');
        $this->table->incr($path,'num_86400');

        return $invocation->proceed();
    }

}