<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Framework\Base;

use Yew\Framework\Exception\Exception;
use Yew\Yew;

/**
 * Action parameter type filtering and dependency injection helper.
 *
 * This trait centralizes the logic used by controller-like components to validate
 * action parameters against their declared reflection types and to resolve
 * non-built-in types via the DI container.
 */
trait ActionParamFilterTrait
{
    /**
     * Validates and optionally filters an action parameter value against its reflection type.
     *
     * @param mixed $value The raw parameter value.
     * @param \ReflectionType|null $type The declared parameter type.
     * @return array [$result, $isValid] where $result is the filtered value and $isValid
     *         indicates whether the value is acceptable for the declared type.
     */
    protected function filterActionParam($value, ?\ReflectionType $type): array
    {
        if ($type === null) {
            return [$value, true];
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->filterSingleTypeActionParam($value, $type);
        }

        if ($type instanceof \ReflectionUnionType) {
            return $this->filterUnionTypeActionParam($value, $type);
        }

        // ReflectionIntersectionType only exists since PHP 8.1; avoid a hard type-hint
        // so the trait remains safe on PHP 8.0.
        if (PHP_VERSION_ID >= 80100 && $type instanceof \ReflectionIntersectionType) {
            return $this->filterIntersectionTypeActionParam($value, $type);
        }

        return [$value, true];
    }

    /**
     * Validates a value against a single named reflection type.
     *
     * @param mixed $value The raw parameter value.
     * @param \ReflectionNamedType $type The declared parameter type.
     * @return array [$result, $isValid]
     */
    protected function filterSingleTypeActionParam($value, \ReflectionNamedType $type): array
    {
        $typeName = $type->getName();

        // Null handling must be checked first.
        if ($value === null) {
            return [null, $type->allowsNull()];
        }

        if ($typeName === 'array') {
            return [$value, is_array($value)];
        }

        // For scalar and other built-in types PHP performs coercion in non-strict mode.
        // We keep the original permissive behavior and only reject values when a type
        // cannot be coerced at all (handled by PHP's own call-time type checking).
        if ($type->isBuiltin()) {
            return [$value, true];
        }

        // Object / interface / enum / mixed: leave further validation to bindInjectedParams().
        return [$value, true];
    }

    /**
     * Validates a value against a union reflection type.
     *
     * A value is valid if it satisfies at least one of the union members.
     *
     * @param mixed $value The raw parameter value.
     * @param \ReflectionUnionType $type The declared union type.
     * @return array [$result, $isValid]
     */
    protected function filterUnionTypeActionParam($value, \ReflectionUnionType $type): array
    {
        foreach ($type->getTypes() as $innerType) {
            if ($innerType instanceof \ReflectionNamedType) {
                [$result, $isValid] = $this->filterSingleTypeActionParam($value, $innerType);
                if ($isValid) {
                    return [$result, true];
                }
            }
        }

        return [$value, false];
    }

    /**
     * Validates a value against an intersection reflection type.
     *
     * A value is valid only if it satisfies all members of the intersection.
     *
     * @param mixed $value The raw parameter value.
     * @param \ReflectionIntersectionType $type The declared intersection type.
     * @return array [$result, $isValid]
     */
    protected function filterIntersectionTypeActionParam($value, $type): array
    {
        foreach ($type->getTypes() as $innerType) {
            if ($innerType instanceof \ReflectionNamedType && !$innerType->isBuiltin()) {
                $className = $innerType->getName();
                if (!is_object($value) || !is_a($value, $className)) {
                    return [$value, false];
                }
            }
        }

        return [$value, true];
    }

    /**
     * Determines whether the declared type can be resolved through the DI container.
     *
     * Only non-built-in named types and union types containing at least one non-built-in
     * named type are considered injectable.
     *
     * @param \ReflectionType|null $type The reflected type of the action parameter.
     * @return bool
     */
    protected function isInjectableType(?\ReflectionType $type): bool
    {
        if ($type === null) {
            return false;
        }

        if ($type instanceof \ReflectionNamedType) {
            return !$type->isBuiltin();
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                if ($innerType instanceof \ReflectionNamedType && !$innerType->isBuiltin()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolves a non-built-in parameter type through the DI container.
     *
     * @param \ReflectionType $type The reflected type of the action parameter.
     * @param string $name The name of the parameter.
     * @param array &$args The array of arguments for the action; this method may append items to it.
     * @param array &$requestedParams The array with requested params; this method may write specific keys to it.
     * @throws \Yew\Framework\Exception\Exception
     * @throws \Yew\Framework\Exception\InvalidConfigException Thrown when there is an error in the DI configuration.
     * @throws \Yew\Framework\Di\NotInstantiableException
     * @throws \ReflectionException
     */
    final protected function bindInjectedParams(\ReflectionType $type, string $name, array &$args, array &$requestedParams): void
    {
        // For union types, pick the first non-built-in member as the DI target.
        if ($type instanceof \ReflectionUnionType) {
            $resolved = null;
            foreach ($type->getTypes() as $innerType) {
                if ($innerType instanceof \ReflectionNamedType && !$innerType->isBuiltin()) {
                    $resolved = $innerType;
                    break;
                }
            }
            if ($resolved === null) {
                throw new Exception('Could not load required service: ' . $name);
            }
            $type = $resolved;
        }

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            throw new Exception('Could not load required service: ' . $name);
        }

        $typeName = $type->getName();

        if (Yew::$container->has($typeName) && ($service = Yew::$container->get($typeName)) instanceof $typeName) {
            $args[] = $service;
            $requestedParams[$name] = "Container DI: $typeName \$$name";
        } elseif ($type->allowsNull()) {
            $args[] = null;
            $requestedParams[$name] = "Unavailable service: $name";
        } else {
            throw new Exception('Could not load required service: ' . $name);
        }
    }
}
