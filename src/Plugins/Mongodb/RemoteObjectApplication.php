<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Mongodb;

class RemoteObjectApplication
{
    /**
     * @return $this
     */
    public function getConfig(): self
    {
        return $this;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        if ($key === "yew.debug") {
            return false;
        }

        return null;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return false;
    }
}
