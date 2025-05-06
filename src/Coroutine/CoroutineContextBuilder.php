<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine;

use Yew\Core\Context\Context;
use Yew\Core\Context\ContextBuilder;

class CoroutineContextBuilder implements ContextBuilder
{
    /**
     * @return Context|null
     */
    public function build(): ?Context
    {
        if (Coroutine::getCid() > 0) {
            return Coroutine::getContext();
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getDeep(): int
    {
        return ContextBuilder::CO_CONTEXT;
    }
}