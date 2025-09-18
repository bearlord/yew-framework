<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Pool;

use Yew\Core\Plugins\Config\BaseConfig;

abstract class Config extends BaseConfig implements ConfigInterface
{
    /**
     * @var string
     */
    protected string $name = "default";

    /**
     * @var array
     */
    protected array $options = [];

    /**
     * @return string
     */
    abstract protected function getKey(): string;
    
    /**
     *
     * @param string $name
     */
    public function __construct(string $name)
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
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     * @return void
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}