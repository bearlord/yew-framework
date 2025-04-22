<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Context;

class RootContextBuilder implements ContextBuilder
{
    private $context;

    /**
     * RootContextBuilder constructor.
     */
    public function __construct()
    {
        $this->context = new Context();
    }

    /**
     * @inheritDoc
     *
     * @return Context|null
     */
    public function build(): ?Context
    {
        return $this->context;
    }

    /**
     * @inheritDoc
     *
     * @return int
     */
    public function getDeep(): int
    {
        return ContextBuilder::ROOT_CONTEXT;
    }
}