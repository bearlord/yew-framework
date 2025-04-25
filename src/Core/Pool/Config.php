<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Pool;

use Yew\Core\Plugins\Config\BaseConfig;

abstract class Config extends BaseConfig
{
    /**
     * @var string
     */
    protected string $name = "default";

    /**
     * @var int
     */
    protected int $poolMaxNumber = 5;

    /**
     * @return string
     */
    abstract protected function getKey(): string;


    /**
     * Config constructor.
     * @param $name
     */
    public function __construct($name)
    {
        parent::__construct($this->getKey(), true, "name");
        $this->setName($name);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getPoolMaxNumber(): int
    {
        return $this->poolMaxNumber;
    }

    /**
     * @param int $poolMaxNumber
     */
    public function setPoolMaxNumber(int $poolMaxNumber): void
    {
        $this->poolMaxNumber = $poolMaxNumber;
    }
}