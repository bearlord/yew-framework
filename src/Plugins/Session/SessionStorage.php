<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Session;

interface SessionStorage
{
    public function get(string $id): ?string;

    public function set(string $id, string $data): void;

    public function remove(string $id): void;
}
