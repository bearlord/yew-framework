<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Server;

use Yew\Core\DI\DI;

use Yew\Coroutine\Channel\ChannelFactory;
use Yew\Coroutine\Coroutine;
use Yew\Coroutine\Event\EventCallFactory;
use Yew\Coroutine\Http\Factory\RequestFactory;
use Yew\Coroutine\Http\Factory\ResponseFactory;
use Yew\Core\Channel\Channel;
use Yew\Core\Plugins\Event\EventCall;
use Yew\Core\Server\Beans\AbstractRequest;
use Yew\Core\Server\Beans\AbstractResponse;
use Yew\Core\Server\Config\ServerConfig;

abstract class Server extends \Yew\Core\Server\Server
{

    public function __construct(?ServerConfig $serverConfig, string $defaultPortClass, string $defaultProcessClass)
    {
        Coroutine::enableCoroutine();
        DI::$definitions = [
            Channel::class => new ChannelFactory(),
            EventCall::class => new EventCallFactory(),
            AbstractRequest::class => new RequestFactory(),
            AbstractResponse::class => new ResponseFactory(),
        ];

        if ($serverConfig == null) {
            $serverConfig = new ServerConfig();
        }

        $serverConfig->setFrameworkDir(dirname(dirname(__DIR__)));

        if ($serverConfig->isDebug()) {
            //error_reporting(E_ALL &  ~E_WARNING);
            error_reporting(E_ALL);
            ini_set("display_errors", "On");
        }

        parent::__construct($serverConfig, $defaultPortClass, $defaultProcessClass);
    }

    public function configure()
    {
        parent::configure();
    }
}