<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route;

use Yew\Core\Plugins\Config\BaseConfig;

class RouteRoleConfig extends BaseConfig
{
    const KEY = "route.role";
    
    /**
     * Name
     * @var string
     */
    protected $name;

    /**
     * Route
     * @var string
     */
    protected $route;

    /**
     * Controller
     * @var string
     */
    protected $controller;

    /**
     * Method
     * @var string
     */
    protected string $method;

    /**
     * Type
     * @var string
     */
    protected string $type;

    /**
     * Allowed port types
     * @var array
     */
    protected array $portTypes = [];

    /**
     * Allow port names
     * @var array
     */
    protected array $portNames = [];

    /**
     * RouteRoleConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::KEY, true, "name");
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * @param mixed $controller
     */
    public function setController($controller): void
    {
        $this->controller = $controller;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    public function buildName()
    {
        $this->name = $this->type . "_" . $this->route;
    }

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @param mixed $route
     */
    public function setRoute($route): void
    {
        $this->route = "/" . trim($route, "/");
    }

    /**
     * @return array
     */
    public function getPortTypes(): array
    {
        return $this->portTypes;
    }

    /**
     * @param array $portTypes
     */
    public function setPortTypes(array $portTypes): void
    {
        $this->portTypes = $portTypes;
    }

    /**
     * @return array
     */
    public function getPortNames(): array
    {
        return $this->portNames;
    }

    /**
     * @param array $portNames
     */
    public function setPortNames(array $portNames): void
    {
        $this->portNames = $portNames;
    }

}