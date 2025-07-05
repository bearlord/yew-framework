<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc\Packer;

/**
 * Interface PackerInterface
 * @package Yew\Plugins\JsonRpc
 */
interface PackerInterface
{
    public function pack($data): string;

    public function unpack(string $data);
}