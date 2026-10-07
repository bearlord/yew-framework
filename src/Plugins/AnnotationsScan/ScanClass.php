<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AnnotationsScan;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\CachedReader;
use ReflectionClass;

class ScanClass
{
    private array $annotationMethod = [];
    private array $annotationClass = [];

    /**
     * @var CachedReader
     */
    private CachedReader $cachedReader;

    /**
     * ScanClass constructor.
     * @param CachedReader $cachedReader
     */
    public function __construct(CachedReader $cachedReader)
    {
        $this->cachedReader = $cachedReader;
    }

    /**
     * Get annotation class
     *
     * @return array
     */
    public function getAnnotationClass(): array
    {
        return $this->annotationClass;
    }

    /**
     * Add annotation class
     *
     * @param $annClass
     * @param ReflectionClass $reflectionClass
     */
    public function addAnnotationClass($annClass, ReflectionClass $reflectionClass)
    {
        if (!array_key_exists($annClass, $this->annotationClass)) {
            $this->annotationClass[$annClass] = [];
        }
        if (!in_array($reflectionClass, $this->annotationClass[$annClass])) {
            $this->annotationClass[$annClass][] = $reflectionClass;
        }
    }

    /**
     * Add annotation method
     * @param string $annClass
     * @param ScanReflectionMethod $reflectionMethod
     */
    public function addAnnotationMethod(string $annClass, ScanReflectionMethod $reflectionMethod)
    {
        if (!array_key_exists($annClass, $this->annotationMethod)) {
            $this->annotationMethod[$annClass] = [];
        }
        if (!in_array($reflectionMethod, $this->annotationMethod[$annClass])) {
            $this->annotationMethod[$annClass][] = $reflectionMethod;
        }
    }

    /**
     * Get related classes by annotating class names
     *
     * @param $annClass
     * @return ReflectionClass[]
     */
    public function findClassesByAnn($annClass): array
    {
        return $this->annotationClass[$annClass] ?? [];
    }

    /**
     * Get cache reader
     *
     * @return CachedReader
     */
    public function getCachedReader(): CachedReader
    {
        return $this->cachedReader;
    }

    /**
     * Get related methods by annotating class names
     *
     * @param $annClass
     * @return ScanReflectionMethod[]
     */
    public function findMethodsByAnnotation($annClass): array
    {
        return $this->annotationMethod[$annClass] ?? [];
    }

    /**
     * Get annotation method
     *
     * @return array
     */
    public function getAnnotationMethod(): array
    {
        return $this->annotationMethod;
    }

    /**
     * Read PHP 8 attribute instances from a class or method reflector.
     *
     * @param ReflectionClass|\ReflectionMethod $reflector
     * @param string|null $annotationName pass null to collect every Annotation-subclass attribute
     * @return object[]
     */
    private function getAttributeInstances(ReflectionClass|\ReflectionMethod $reflector, ?string $annotationName): array
    {
        // When a specific annotation is requested, IS_INSTANCEOF already filters
        // to its subclasses; otherwise we keep every Annotation-subclass attribute.
        $attributes = $annotationName === null
            ? array_filter(
                $reflector->getAttributes(),
                static fn(\ReflectionAttribute $a) => is_subclass_of($a->getName(), Annotation::class)
            )
            : $reflector->getAttributes($annotationName, \ReflectionAttribute::IS_INSTANCEOF);

        $instances = [];
        foreach ($attributes as $attribute) {
            try {
                $instances[] = self::instantiateAttribute($attribute);
            } catch (\Throwable $e) {
                // A malformed attribute must not abort the whole scan; skip it.
            }
        }
        return $instances;
    }

    /**
     * Instantiate a PHP 8 attribute while staying compatible with Doctrine's
     * final `Annotation::__construct(array $data)`.
     *
     * Doctrine feeds docblock annotations a single associative array; a native
     * attribute must be normalised to the same shape so the `array $data`
     * constructor can accept it:
     *  - no arguments   -> empty property array              (#[Foo])
     *  - one scalar     -> mapped to the `value` property    (#[Foo("x")])
     *  - one array      -> used directly as the data array   (#[Foo(["value"=>"x"])])
     *  - multiple args  -> passed as-is (named args map to
     *                      properties; positional multi-args degrade gracefully)
     *
     * We always call `new $class($array)` instead of `newInstance()`, because
     * newInstance() spreads named arguments and would fail against the single
     * `array $data` parameter.
     *
     * @param \ReflectionAttribute $attribute
     * @return object
     */
    public static function instantiateAttribute(\ReflectionAttribute $attribute): object
    {
        $arguments = $attribute->getArguments();
        $className = $attribute->getName();

        if (empty($arguments)) {
            return new $className([]);
        }

        if (count($arguments) === 1) {
            $arg = $arguments[0];
            return new $className(is_array($arg) ? $arg : ['value' => $arg]);
        }

        return new $className($arguments);
    }

    /**
     * Get class and interface annotation
     *
     * @param ReflectionClass $class
     * @param $annotationName
     * @return object|null
     */
    public function getClassAndInterfaceAnnotation(ReflectionClass $class, $annotationName): ?object
    {
        $result = $this->cachedReader->getClassAnnotation($class, $annotationName);
        if ($result === null) {
            // No docblock match: fall back to the first PHP 8 attribute of this type.
            $result = $this->getAttributeInstances($class, $annotationName)[0] ?? null;
        }
        if ($result === null) {
            foreach ($class->getInterfaces() as $interface) {
                $result = $this->getClassAndInterfaceAnnotation($interface, $annotationName);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return $result;
    }

    /**
     * Get class and interface annotation list
     *
     * @param ReflectionClass $class
     * @return array
     */
    public function getClassAndInterfaceAnnotations(ReflectionClass $class): array
    {
        $result = $this->cachedReader->getClassAnnotations($class);
        $result = array_merge($result, $this->getAttributeInstances($class, null));
        foreach ($class->getInterfaces() as $interface) {
            $result = array_merge($this->getClassAndInterfaceAnnotations($interface), $result);
        }
        return $result;
    }

    /**
     * Get method and interface annotation
     *
     * @param \ReflectionMethod $method
     * @param $annotationName
     * @return mixed
     */
    public function getMethodAndInterfaceAnnotation(\ReflectionMethod $method, $annotationName)
    {
        $result = $this->cachedReader->getMethodAnnotation($method, $annotationName);
        if ($result === null) {
            $result = $this->getAttributeInstances($method, $annotationName)[0] ?? null;
        }
        if ($result === null) {
            foreach ($method->getDeclaringClass()->getInterfaces() as $interface) {
                try {
                    $interfaceMethod = $interface->getMethod($method->getName());
                } catch (\Throwable $e) {
                    $interfaceMethod = null;
                }
                if ($interfaceMethod !== null) {
                    $result = $this->getMethodAndInterfaceAnnotation($interfaceMethod, $annotationName);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get method and interface annotation list
     *
     * @param \ReflectionMethod $method
     * @return array
     */
    public function getMethodAndInterfaceAnnotations(\ReflectionMethod $method): array
    {
        $result = $this->cachedReader->getMethodAnnotations($method);
        $result = array_merge($result, $this->getAttributeInstances($method, null));
        foreach ($method->getDeclaringClass()->getInterfaces() as $interface) {
            try {
                $interfaceMethod = $interface->getMethod($method->getName());
            } catch (\Throwable $e) {
                $interfaceMethod = null;
            }
            if ($interfaceMethod !== null) {
                $result = array_merge($result, $this->getMethodAndInterfaceAnnotations($interfaceMethod));
            }
        }
        return $result;
    }
}