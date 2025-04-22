<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Exception;

class ConfigException extends Exception
{

    /**
     * @param object $object
     * @param string $field
     * @param string|null $value
     * @throws ConfigException
     */
    public static function AssertNull(object $object, string $field, ?string $value = null)
    {
        if ($value === null) {
            $name = get_class($object);
            throw new static("[{$name}] {$field} cannot be empty");
        }
    }

}