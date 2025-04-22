<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Process;

use Yew\Core\Context\Context;
use Yew\Core\Context\ContextBuilder;

/**
 * Class ProcessContextBuilder
 * @package Yew\Core\Server\Process
 */
class ProcessContextBuilder implements ContextBuilder
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Process $process)
    {
        $this->context = new Context();
        $this->context->add("process", $process);
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
        return ContextBuilder::PROCESS_CONTEXT;
    }
}