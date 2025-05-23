<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Di;

use Yew\Yew;
use Yew\Framework\Exception\InvalidConfigException;

/**
 * Instance represents a reference to a named object in a dependency injection (DI) container or a service locator.
 *
 * You may use [[get()]] to obtain the actual object referenced by [[id]].
 *
 * Instance is mainly used in two places:
 *
 * - When configuring a dependency injection container, you use Instance to reference a class name, interface name
 *   or alias name. The reference can later be resolved into the actual object by the container.
 * - In classes which use service locator to obtain dependent objects.
 *
 * The following example shows how to configure a DI container with Instance:
 *
 * ```php
 * $container = new \Yew\Framework\Di\Container;
 * $container->set('cache', [
 *     'class' => 'Yew\Framework\Caching\DbCache',
 *     'db' => Instance::of('db')
 * ]);
 * $container->set('db', [
 *     'class' => 'Yew\Framework\Db\Connection',
 *     'dsn' => 'sqlite:path/to/file.db',
 * ]);
 * ```
 *
 * And the following example shows how a class retrieves a component from a service locator:
 *
 * ```php
 * class DbCache extends Cache
 * {
 *     public $db = 'db';
 *
 *     public function init()
 *     {
 *         parent::init();
 *         $this->db = Instance::ensure($this->db, 'Yew\Framework\Db\Connection');
 *     }
 * }
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Instance
{
    /**
     * @var string|null the component ID, class name, interface name or alias name
     */
    public ?string $id = null;


    /**
     * Constructor.
     * @param string|null $id the component ID
     */
    protected function __construct(?string $id = null)
    {
        $this->id = $id;
    }

    /**
     * Creates a new Instance object.
     * @param string|null $id the component ID
     * @return Instance the new Instance object.
     */
    public static function of(?string $id = null): Instance
    {
        return new static($id);
    }

    /**
     * Resolves the specified reference into the actual object and makes sure it is of the specified type.
     *
     * The reference may be specified as a string or an Instance object. If the former,
     * it will be treated as a component ID, a class/interface name or an alias, depending on the container type.
     *
     * If you do not specify a container, the method will first try `Yew::$app` followed by `Yew::$container`.
     *
     * For example,
     *
     * ```php
     * use Yew\Framework\Db\Connection;
     *
     * // returns Yew::$app->db
     * $db = Instance::ensure('db', Connection::className());
     * // returns an instance of Connection using the given configuration
     * $db = Instance::ensure(['dsn' => 'sqlite:path/to/my.db'], Connection::className());
     * ```
     *
     * @param object|string|array|static $reference an object or a reference to the desired object.
     * You may specify a reference in terms of a component ID or an Instance object.
     * Starting from version 2.0.2, you may also pass in a configuration array for creating the object.
     * If the "class" value is not specified in the configuration array, it will use the value of `$type`.
     * @param string|null $type the class/interface name to be checked. If null, type check will not be performed.
     * @param ServiceLocator|Container $container the container. This will be passed to [[get()]].
     * @return object the object referenced by the Instance, or `$reference` itself if it is an object.
     * @throws InvalidConfigException|\ReflectionException if the reference is invalid
     */
    public static function ensure($reference, ?string $type = null, $container = null)
    {
        if (is_array($reference)) {
            $class = $reference['class'] ?? $type;
            if (!$container instanceof Container) {
                $container = Yew::$container;
            }
            unset($reference['class']);
            $component = $container->get($class, [], $reference);
            if ($type === null || $component instanceof $type) {
                return $component;
            }

            throw new InvalidConfigException('Invalid data type: ' . $class . '. ' . $type . ' is expected.');
        } elseif (empty($reference)) {
            throw new InvalidConfigException('The required component is not specified.');
        }

        if (is_string($reference)) {
            $reference = new static($reference);
        } elseif ($type === null || $reference instanceof $type) {
            return $reference;
        }

        if ($reference instanceof self) {
            try {
                $component = $reference->get($container);
            } catch (\ReflectionException $e) {
                throw new InvalidConfigException('Failed to instantiate component or class "' . $reference->id . '".', 0, $e);
            }
            if ($type === null || $component instanceof $type) {
                return $component;
            }

            throw new InvalidConfigException('"' . $reference->id . '" refers to a ' . get_class($component) . " component. $type is expected.");
        }

        $valueType = is_object($reference) ? get_class($reference) : gettype($reference);
        throw new InvalidConfigException("Invalid data type: $valueType. $type is expected.");
    }

    /**
     * Returns the actual object referenced by this Instance object.
     * @param null $container the container used to locate the referenced object.
     * If null, the method will first try `Yew::$app` then `Yew::$container`.
     * @return object the actual object referenced by this Instance object.
     * @throws InvalidConfigException
     * @throws NotInstantiableException
     * @throws \ReflectionException
     */
    public function get($container = null): object
    {
        if ($container) {
            return $container->get($this->id);
        }
        if (Yew::$app && Yew::$app->has($this->id)) {
            return Yew::$app->get($this->id);
        }

        return Yew::$container->get($this->id);
    }

    /**
     * Restores class state after using `var_export()`.
     *
     * @param array $state
     * @return Instance
     * @throws InvalidConfigException when $state property does not contain `id` parameter
     * @see var_export()
     * @since 2.0.12
     */
    public static function __set_state(array $state)
    {
        if (!isset($state['id'])) {
            throw new InvalidConfigException('Failed to instantiate class "Instance". Required parameter "id" is missing');
        }

        return new self($state['id']);
    }
}
