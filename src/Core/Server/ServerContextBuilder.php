<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server;

use Yew\Core\Context\Context;
use Yew\Core\Context\ContextBuilder;

class ServerContextBuilder implements ContextBuilder
{
    protected Context $context;

    /**
     * ServerContextBuilder constructor.
     *
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->context = new Context();
        $this->context->add("server", $server);
    }

    /**
     * @return Context|null
     */
    public function build(): ?Context
    {
        return $this->context;
    }

    /**
     * @return int
     */
    public function getDeep(): int
    {
        return ContextBuilder::SERVER_CONTEXT;
    }
}