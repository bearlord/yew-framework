<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Config;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Core\Exception\ConfigException;
use Yew\Core\Server\Process\Process;

/**
 * Class ProcessConfig
 * @package Yew\Core\Server\Config
 */
class ProcessConfig extends BaseConfig
{
    const key = "yew.process";

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $groupName;

    /**
     * @param string|null $className
     * @param string|null $name
     * @param string|null $groupName
     * @throws ConfigException
     */
    public function __construct(?string $className = '', ?string $name = '', ?string $groupName = 'DefaultGroup')
    {
        parent::__construct(self::key, true, "name");
        if ($groupName == Process::WORKER_GROUP) {
            throw new ConfigException("The custom process is not allowed to use the WORKER_GROUP group name");
        }
        $this->groupName = $groupName;
        $this->name = $name;
        $this->className = $className;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @param mixed $className
     */
    public function setClassName($className): void
    {
        $this->className = $className;
    }

    /**
     * @return string|null
     */
    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    /**
     * @param mixed $groupName
     */
    public function setGroupName($groupName): void
    {
        $this->groupName = $groupName;
    }
}
