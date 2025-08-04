<?php

namespace Yew\Core\Node;

use DI\Container;
use Yew\Core\Log\LoggerInterface;
use Yew\Core\DI\DI;
use Yew\Core\Log\Log;

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
    }



    /**
     * @return LoggerInterface
     */
    public function getLog(): LoggerInterface
    {
        return DI::getInstance()->get(LoggerInterface::class);
    }
}