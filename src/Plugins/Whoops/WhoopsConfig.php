<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Whoops;

use Yew\Core\Plugins\Config\BaseConfig;

class WhoopsConfig extends BaseConfig
{
    const KEY = "whoops";

    /**
     * @var bool
     */
    protected bool $enable = true;

    /**
     * WhoopsConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enable;
    }

    /**
     * @param bool $enable
     */
    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }
}