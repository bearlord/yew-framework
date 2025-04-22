<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Console;

use Yew\Core\Plugins\Config\BaseConfig;

/**
 * Class ConsoleConfig
 * @package Yew\Plugins\Console
 */
class ConsoleConfig extends BaseConfig
{
    const KEY = "console";
    /**
     * @var string[]
     */
    protected $cmdClassList = [];

    /**
     * ConsoleConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return string[]
     */
    public function getCmdClassList(): array
    {
        return $this->cmdClassList;
    }

    /**
     * @param string[] $cmdClassList
     */
    public function setCmdClassList(array $cmdClassList): void
    {
        $this->cmdClassList = $cmdClassList;
    }

    /**
     * @param string $className
     */
    public function addCmdClass(string $className): void
    {
        $list = explode("\\", $className);
        $this->cmdClassList[$list[count($list) - 1]] = $className;
    }

}