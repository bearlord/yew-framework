<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Validate\Annotation;

use Yew\Plugins\Validate\ValidationException;
use ReflectionClass;

class ValidatedFilter
{
    /**
     * @param ReflectionClass|string $reflectionClass
     * @param $values
     * @param array $roles
     * @param array $messages
     * @param array $translates
     * @param string $scene
     * @return array|\stdClass
     * @throws ValidationException
     * @throws \ReflectionException
     */
    public static function valid($reflectionClass, $values, $roles = [], $messages = [], $translates = [], $scene = "")
    {
        $result = Filter::filter($reflectionClass, $values);
        $result = Validated::valid($reflectionClass, $result, $roles, $messages, $translates, $scene);
        return $result;
    }
}