<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Web;

use ArrayIterator;
use Yew\Yew;
use Yew\Framework\Base\BaseObject;
use Yew\Framework\Exception\InvalidCallException;

/**
 * CookieCollection maintains the cookies available in the current request.
 *
 * For more details and usage information on CookieCollection, see the [guide article on handling cookies](guide:runtime-sessions-cookies).
 *
 * @property int $count The number of cookies in the collection. This property is read-only.
 * @property ArrayIterator $iterator An iterator for traversing the cookies in the collection. This property
 * is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class CookieCollection extends BaseObject implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var bool whether this collection is read only.
     */
    public bool $readOnly = false;

    /**
     * @var Cookie[] the cookies in this collection (indexed by the cookie names)
     */
    private array $_cookies;


    /**
     * Constructor.
     * @param array $cookies the cookies that this collection initially contains. This should be
     * an array of name-value pairs.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct(array $cookies = [], array $config = [])
    {
        $this->_cookies = $cookies;
        parent::__construct($config);
    }

    /**
     * Returns an iterator for traversing the cookies in the collection.
     * This method is required by the SPL interface [[\IteratorAggregate]].
     * It will be implicitly called when you use `foreach` to traverse the collection.
     * @return ArrayIterator an iterator for traversing the cookies in the collection.
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->_cookies);
    }

    /**
     * Returns the number of cookies in the collection.
     * This method is required by the SPL `Countable` interface.
     * It will be implicitly called when you use `count($collection)`.
     * @return int the number of cookies in the collection.
     */
    public function count(): int
    {
        return $this->getCount();
    }

    /**
     * Returns the number of cookies in the collection.
     * @return int the number of cookies in the collection.
     */
    public function getCount(): int
    {
        return count($this->_cookies);
    }

    /**
     * Returns the cookie with the specified name.
     * @param string $name the cookie name
     * @return Cookie the cookie with the specified name. Null if the named cookie does not exist.
     * @see getValue()
     */
    public function get(string $name)
    {
        return $this->_cookies[$name] ?? null;
    }

    /**
     * Returns the value of the named cookie.
     * @param string $name the cookie name
     * @param mixed $defaultValue the value that should be returned when the named cookie does not exist.
     * @return mixed the value of the named cookie.
     * @see get()
     */
    public function getValue(string $name, $defaultValue = null)
    {
        return isset($this->_cookies[$name]) ? $this->_cookies[$name]->value : $defaultValue;
    }

    /**
     * Returns whether there is a cookie with the specified name.
     * Note that if a cookie is marked for deletion from browser, this method will return false.
     * @param string $name the cookie name
     * @return bool whether the named cookie exists
     * @see remove()
     */
    public function has(string $name)
    {
        return isset($this->_cookies[$name]) && $this->_cookies[$name]->value !== ''
            && ($this->_cookies[$name]->expire === null || $this->_cookies[$name]->expire === 0 || $this->_cookies[$name]->expire >= time());
    }

    /**
     * Adds a cookie to the collection.
     * If there is already a cookie with the same name in the collection, it will be removed first.
     * @param Cookie $cookie the cookie to be added
     * @throws InvalidCallException if the cookie collection is read only
     */
    public function add(Cookie $cookie)
    {
        if ($this->readOnly) {
            throw new InvalidCallException('The cookie collection is read only.');
        }
        $this->_cookies[$cookie->name] = $cookie;
    }

    /**
     * Removes a cookie.
     * If `$removeFromBrowser` is true, the cookie will be removed from the browser.
     * In this case, a cookie with outdated expiry will be added to the collection.
     * @param Cookie|string $cookie the cookie object or the name of the cookie to be removed.
     * @param bool $removeFromBrowser whether to remove the cookie from browser
     * @throws InvalidCallException if the cookie collection is read only
     */
    public function remove($cookie, bool $removeFromBrowser = true)
    {
        if ($this->readOnly) {
            throw new InvalidCallException('The cookie collection is read only.');
        }
        if ($cookie instanceof Cookie) {
            $cookie->expire = 1;
            $cookie->value = '';
        } else {
            $cookie = Yew::createObject([
                'class' => 'yii\web\Cookie',
                'name' => $cookie,
                'expire' => 1,
            ]);
        }
        if ($removeFromBrowser) {
            $this->_cookies[$cookie->name] = $cookie;
        } else {
            unset($this->_cookies[$cookie->name]);
        }
    }

    /**
     * Removes all cookies.
     * @throws InvalidCallException if the cookie collection is read only
     */
    public function removeAll()
    {
        if ($this->readOnly) {
            throw new InvalidCallException('The cookie collection is read only.');
        }
        $this->_cookies = [];
    }

    /**
     * Returns the collection as a PHP array.
     * @return array the array representation of the collection.
     * The array keys are cookie names, and the array values are the corresponding cookie objects.
     */
    public function toArray(): array
    {
        return $this->_cookies;
    }

    /**
     * Populates the cookie collection from an array.
     * @param array $array the cookies to populate from
     * @since 2.0.3
     */
    public function fromArray(array $array)
    {
        $this->_cookies = $array;
    }

    /**
     * Returns whether there is a cookie with the specified name.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `isset($collection[$name])`.
     * @param string $offset the cookie name
     * @return bool whether the named cookie exists
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Returns the cookie with the specified name.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `$cookie = $collection[$name];`.
     * This is equivalent to [[get()]].
     * @param string $offset the cookie name
     * @return Cookie the cookie with the specified name, null if the named cookie does not exist.
     */
    public function offsetGet($offset): ?Cookie
    {
        return $this->get($offset);
    }

    /**
     * Adds the cookie to the collection.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `$collection[$name] = $cookie;`.
     * This is equivalent to [[add()]].
     * @param string $offset the cookie name
     * @param Cookie $value the cookie to be added
     */
    public function offsetSet($offset, $value)
    {
        $this->add($value);
    }

    /**
     * Removes the named cookie.
     * This method is required by the SPL interface [[\ArrayAccess]].
     * It is implicitly called when you use something like `unset($collection[$name])`.
     * This is equivalent to [[remove()]].
     * @param string $offset the cookie name
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
