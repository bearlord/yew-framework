<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security;

use Yew\Core\Plugins\Config\BaseConfig;

/**
 * Class SecurityConfig
 * @package Yew\Plugins\Security
 */
class SecurityConfig extends BaseConfig
{
    const KEY = "security";

    /**
     * SecurityConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }
}