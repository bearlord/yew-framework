<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

class IpcCallData
{
    /**
     * @var string
     */
    private string $className;
    /**
     * @var string
     */
    private string $name;
    /**
     * @var array
     */
    private array $arguments;
    /**
     * @var int
     */
    private int $token;
    /**
     * @var bool
     */
    private bool $oneway;

    /**
     * @param string $className
     * @param string $name
     * @param array $arguments
     * @param bool $oneway
     */
    public function __construct(string $className, string $name, array $arguments,bool $oneway)
    {
        $this->className = $className;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->token = IpcManager::getToken();
        $this->oneway = $oneway;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return int
     */
    public function getToken(): int
    {
        return $this->token;
    }

    /**
     * @return bool
     */
    public function isOneway(): bool
    {
        return $this->oneway;
    }
}