<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 * copy from hyperf[https://www.hyperf.io/]
 */

namespace Yew\Core\Pool;

interface FrequencyInterface
{

    public function __construct(?Pool $pool = null);

    /**
     * Number of hit per time.
     * @param int $number
     * @return bool
     */
    public function hit(int $number = 1): bool;

    /**
     * Hits per second.
     * @return float
     */
    public function frequency(): float;

    /**
     * @return bool
     */
    public function isLowFrequency(): bool;

}
