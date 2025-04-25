<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Channel;

interface Channel
{
    /**
     * Push data
     *
     * @param $data
     * @param float $timeout
     * @return bool
     */
    public function push($data, float $timeout = -1): bool;

    /**
     * Pop data
     *
     * @param float $timeout
     * @return mixed
     */
    public function pop(float $timeout = 0);

    /**
     * Length
     *
     * @return int
     */
    public function length(): int;

    /**
     * Is empty
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Is full
     *
     * @return bool
     */
    public function isFull(): bool;

    /**
     * Get capacity
     *
     * @return int
     */
    public function getCapacity(): int;

    /**
     * Close
     *
     * @return mixed
     */
    public function close();

    /**
     * PopLoop
     *
     * @param callable $callback
     * @return mixed
     */
    public function popLoop(callable $callback);

    /**
     * @return int
     */
    public function getErrCode(): int;

    /**
     * @return bool
     */
    public function isClosing(): bool;

    /**
     * @return bool
     */
    public function isTimeout(): bool;

    /**
     * @return bool
     */
    public function isAvailable(): bool;
}