<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Validate\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\CachedReader;
use Inhere\Validate\Filter\Filtration;
use ReflectionClass;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
class Filter extends Annotation
{
    protected static array $cache = [];

    /**
     * Absolute value
     * @var bool
     */
    public bool $abs = false;

    /**
     * Filter illegal characters and convert to int
     * @var bool
     */
    public bool $integer = false;

    /**
     * Convert to bool
     * @var bool
     */
    public bool $boolean = false;

    /**
     * Filter illegal characters and retain data in float format
     * @var bool
     */
    public bool $float = false;

    /**
     * Filter illegal characters and convert to string
     * @var bool
     */
    public bool $string = false;

    /**
     * Remove leading and trailing whitespace characters, support for arrays.
     * @var bool
     */
    public bool $trim = false;

    /**
     * Convert \n \r \n \r to <br/>
     * @var bool
     */
    public bool $nl2br = false;

    /**
     * Convert string to lower case
     * @var bool
     */
    public bool $lowercase = false;

    /**
     * String to uppercase
     * @var bool
     */
    public bool $uppercase = false;

    /**
     * Convert string to snake style
     * @var bool
     */
    public bool $snakeCase = false;

    /**
     * Convert string to camel style
     * @var bool
     */
    public bool $camelCase = false;

    /**
     * Convert string to time
     * @var bool
     */
    public bool $strToTime = false;

    /**
     * URL filtering, removing all characters that do not match the URL
     * @var bool
     */
    public bool $url = false;

    /**
     * String to array 'tag0, tag1'-> ['tag0', 'tag1']
     * @var bool
     */
    public bool $str2array = false;

    /**
     * Remove duplicate values from an array (by array_unique ())
     * @var bool
     */
    public bool $unique = false;

    /**
     * email filtering, remove all characters that do not match email
     * @var bool
     */
    public bool $email = false;

    /**
     * Removes characters not needed for URL encoding, similar to urlencode () function
     * @var bool
     */
    public bool $encoded = false;

    /**
     * Clear spaces
     * @var bool
     */
    public bool $clearSpace = false;

    /**
     * Clean up newlines
     * @var bool
     */
    public bool $clearNewline = false;

    /**
     * Equivalent to using strip_tags()
     * @var bool
     */
    public bool $stripTags = false;

    /**
     * Equivalent to escaping data using htmlspecialchars()
     * @var bool
     */
    public bool $escape = false;

    /**
     * Apply addslashes() to escape data
     * @var bool
     */
    public bool $quotes = false;

    public function build($name): ?array
    {
        $result = [$name];
        $filter = [];
        foreach ($this as $key => $value) {
            if ($value === true) {
                $filter[] = $key;
            }
        }
        if (!empty($filter)) {
            $result[] = implode("|", $filter);
            return $result;
        } else {
            return null;
        }
    }


    /**
     * @param ReflectionClass|string $reflectionClass
     * @param $values
     * @return array
     * @throws \ReflectionException
     */
    public static function filter($reflectionClass, $values): array
    {
        $filterRole = self::buildRole($reflectionClass);
        if (!empty($filterRole)) {
            $result = Filtration::make($values, $filterRole)->filtering();
            foreach ($filterRole as $role) {
                $values[$role[0]] = $result[$role[0]];
            }
        }
        return $values;
    }

    /**
     * @param ReflectionClass|string $reflectionClass
     * @return array
     * @throws \ReflectionException
     * @throws \Exception
     */
    public static function buildRole($reflectionClass): array
    {
        if (is_string($reflectionClass)) {
            if (array_key_exists($reflectionClass, self::$cache)) {
                return self::$cache[$reflectionClass];
            }
            $reflectionClass = new ReflectionClass($reflectionClass);
        }
        if (array_key_exists($reflectionClass->name, self::$cache)) {
            return self::$cache[$reflectionClass->name];
        }
        $filterRole = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $filters = DIget(CachedReader::class)->getPropertyAnnotations($property);
            foreach ($filters as $filter) {
                if ($filter instanceof Filter) {
                    $one = $filter->build($property->name);
                    if (!empty($one)) {
                        $filterRole[] = $one;
                    }
                }
            }
        }
        self::$cache[$reflectionClass->name] = $filterRole;
        return $filterRole;
    }
}