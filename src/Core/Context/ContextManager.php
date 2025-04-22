<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Context;

class ContextManager
{
    /**
     * @var ContextManager|null
     */
    protected static ?ContextManager $instance = null;

    /**
     * @var ContextBuilder[]
     */
    protected array $contextStack = [];

    /**
     * ContextManager constructor.
     */
    public function __construct()
    {
        $this->registerContext(new RootContextBuilder());
    }

    /**
     * Register context
     * @param ContextBuilder $contextBuilder
     */
    public function registerContext(ContextBuilder $contextBuilder)
    {
        $this->contextStack[$contextBuilder->getDeep()] = $contextBuilder;
        krsort($this->contextStack);
    }

    /**
     * Instance
     * @return ContextManager
     */
    public static function getInstance(): ContextManager
    {
        if (self::$instance == null) {
            self::$instance = new ContextManager();
        }
        return self::$instance;
    }

    /**
     * Get context builder
     * @param string $deep
     * @param callable|null $register
     * @return ContextBuilder
     */
    public function getContextBuilder(string $deep, ?callable $register = null): ContextBuilder
    {
        $result = $this->contextStack[$deep] ?? null;
        if ($result == null && $register != null) {
            $result = $register();
            $this->registerContext($result);
        }
        return $result;
    }

    /**
     * Get context
     * @return Context|null
     */
    public function getContext(): ?Context
    {
        $context = null;
        $parentContext = null;
        foreach ($this->contextStack as $contextBuilder) {
            if ($context != null) {
                $parentContext = $contextBuilder->build();
                if ($parentContext != null) {
                    break;
                }
            } else {
                $context = $contextBuilder->build();
            }
        }
        if ($context != null && $context->getParentContext() == null) {
            $context->setParentContext($parentContext);
        }
        return $context ?? new Context($parentContext);
    }
}