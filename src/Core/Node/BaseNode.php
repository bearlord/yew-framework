<?php

namespace Yew\Core\Node;

use DI\Container;
use Yew\Core\Log\LoggerInterface;
use Yew\Core\DI\DI;
use Yew\Core\Log\Log;
use Yew\Core\Log\Logger;

class BaseNode
{

    /**
     * @var Container
     */
    protected Container $container;


    public function __construct()
    {
        //Get DI container
        $this->container = DI::getInstance()->getContainer();


        //Set the default Log
        $this->container->set(LoggerInterface::class, new Log());
        $this->container->set(Logger::class, new Logger());
    }



    /**
     * @return LoggerInterface
     */
    public function getLog(): LoggerInterface
    {
        return DI::getInstance()->get(LoggerInterface::class);
    }
}