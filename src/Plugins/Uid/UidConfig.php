<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Uid;

use Yew\Core\Plugins\Config\BaseConfig;

class UidConfig extends BaseConfig
{
    const KEY = "uid";
    
    protected $uidMaxLength = 24;

    /**
     * UidConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * @return int
     */
    public function getUidMaxLength(): int
    {
        return $this->uidMaxLength;
    }

    /**
     * @param int $uidMaxLength
     */
    public function setUidMaxLength(int $uidMaxLength): void
    {
        $this->uidMaxLength = $uidMaxLength;
    }
}