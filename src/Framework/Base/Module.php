<?php

namespace Yew\Framework\Base;

use Yew\Framework\Di\ServiceLocator;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Exception\InvalidRouteException;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Plugins\Route\RoutePlugin;
use Yew\Yew;
use Yew\Framework\Exception\InvalidArgumentException;


class Module extends ServiceLocator
{

    /**
     * @event ActionEvent an event raised before executing a controller action.
     * You may set [[ActionEvent::isValid]] to be `false` to cancel the action execution.
     */
    const EVENT_BEFORE_ACTION = 'beforeAction';

    /**
     * @event ActionEvent an event raised after executing a controller action.
     */
    const EVENT_AFTER_ACTION = 'afterAction';
    
    /**
     * @var string an ID that uniquely identifies this module among other modules which have the same [[module|parent]].
     */
    public ?string $id = null;

    /**
     * @var Module|null the parent module of this module. `null` if this module does not have a parent.
     */
    public $module;

    /**
     * @var array mapping from controller ID to controller configurations.
     * Each name-value pair specifies the configuration of a single controller.
     * A controller configuration can be either a string or an array.
     * If the former, the string should be the fully qualified class name of the controller.
     * If the latter, the array must contain a `class` element which specifies
     * the controller's fully qualified class name, and the rest of the name-value pairs
     * in the array are used to initialize the corresponding controller properties. For example,
     *
     * ```php
     * [
     *   'account' => 'App\Controller\UserController',
     *   'article' => [
     *      'class' => 'App\Controller\PostController',
     *      'pageTitle' => 'something new',
     *   ],
     * ]
     * ```
     */
    public array $controllerMap = [];

    /**
     * @var string|null the namespace that controller classes are in.
     * This namespace will be used to load controller classes by prepending it to the controller
     * class name.
     *
     * If not set, it will use the `controllers` sub-namespace under the namespace of this module.
     * For example, if the namespace of this module is `foo\bar`, then the default
     * controller namespace would be `foo\bar\controllers`.
     *
     * See also the [guide section on autoloading](guide:concept-autoloading) to learn more about
     * defining namespaces and how classes are loaded.
     */
    public ?string $controllerNamespace = null;

    /**
     * @var string the default route of this module. Defaults to `default`.
     * The route may consist of child module ID, controller ID, and/or action ID.
     * For example, `help`, `post/create`, `admin/post/create`.
     * If action ID is not given, it will take the default value as specified in
     * [[Controller::defaultAction]].
     */
    public string $defaultRoute = 'default';

    /**
     * @var string the root directory of the module.
     */
    private ?string $_basePath = null;

    /**
     * @var array child modules of this module
     */
    private array $_modules = [
        'generate' => ['class' => 'Yew\Framework\Generator\Module'],
    ];

    /**
     * Constructor.
     * @param string $id the ID of this module.
     * @param Module|null $parent the parent module (if any).
     * @param array $config name-value pairs that will be used to initialize the object properties.
     */
    public function __construct(string $id, ?Module $parent = null, ?array $config = [])
    {
        $this->id = $id;
        $this->module = $parent;
        parent::__construct($config);
    }

    /**
     * Returns the currently requested instance of this module class.
     * If the module class is not currently requested, `null` will be returned.
     * This method is provided so that you access the module instance from anywhere within the module.
     * @return static|null the currently requested instance of this module class, or `null` if the module class is not requested.
     */
    public static function getInstance()
    {
        $class = get_called_class();
        return isset(Yew::$app->loadedModules[$class]) ? Yew::$app->loadedModules[$class] : null;
    }

    /**
     * Sets the currently requested instance of this module class.
     * @param Module|null $instance the currently requested instance of this module class.
     * If it is `null`, the instance of the calling class will be removed, if any.
     */
    public static function setInstance(?Module $instance)
    {
        if ($instance === null) {
            unset(Yew::$app->loadedModules[get_called_class()]);
        } else {
            Yew::$app->loadedModules[get_class($instance)] = $instance;
        }
    }


    /**
     * Returns the root directory of the module.
     * It defaults to the directory containing the module class file.
     * @return string the root directory of the module.
     */
    public function getBasePath(): string
    {
        if ($this->_basePath === null) {
            $class = new \ReflectionClass($this);
            $this->_basePath = dirname($class->getFileName());
        }

        return $this->_basePath;
    }

    /**
     * Sets the root directory of the module.
     * This method can only be invoked at the beginning of the constructor.
     * @param string $path the root directory of the module. This can be either a directory name or a [path alias](guide:concept-aliases).
     * @throws InvalidArgumentException if the directory does not exist.
     */
    public function setBasePath(string $path)
    {
        $path = Yew::getAlias($path);
        $p = strncmp($path, 'phar://', 7) === 0 ? $path : realpath($path);
        if (is_string($p) && is_dir($p)) {
            $this->_basePath = $p;
        } else {
            throw new InvalidArgumentException("The directory does not exist: $path");
        }
    }


    /**
     * @param string $route
     * @return array
     * @throws InvalidConfigException
     */
    public function createController(string $route): ?array
    {
        var_dump($route);
        if ($route === '') {
            $route = $this->defaultRoute;
        }

        // double slashes or leading/ending slashes may cause substr problem
        $route = trim($route, '/');
        if (strpos($route, '//') !== false) {
            return null;
        }


        //$route = "/" . trim($route, "/");

        if (strpos($route, '/') !== false) {
            list($id, $route) = explode('/', $route, 2);
        } else {
            $id = $route;
            $route = '';
        }

        var_dump($this->controllerMap);

        // module and controller map take precedence
        if (isset($this->controllerMap[$id])) {
            $controller = Yew::createObject($this->controllerMap[$id], [$id, $this]);
            return [$controller, $route];
        }

        $module = $this->getModule($id);

        var_dump($id, $module, $route);

        if ($module !== null) {
            return $module->createController($route);
        }

        var_dump($route);

        return null;

        $method = $this->request->server('request_method');
        $port = $this->request->server('server_port');
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch($port . ":" . $method, $route);

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                $controllerName = $handler[0]->name;
                $actionName = $handler[1]->name;
                $controller = Yew::createObject([
                    'class' => $controllerName
                ], [$id, $this]);

                return [$controller, $actionName];
        }
        return null;
    }

    /**
     * Checks whether the child module of the specified ID exists.
     * This method supports checking the existence of both child and grand child modules.
     * @param string $id module ID. For grand child modules, use ID path relative to this module (e.g. `admin/content`).
     * @return bool whether the named module exists. Both loaded and unloaded modules
     * are considered.
     */
    public function hasModule($id)
    {
        if (($pos = strpos($id, '/')) !== false) {
            // sub-module
            $module = $this->getModule(substr($id, 0, $pos));

            return $module === null ? false : $module->hasModule(substr($id, $pos + 1));
        }

        return isset($this->_modules[$id]);
    }

    /**
     * Retrieves the child module of the specified ID.
     * This method supports retrieving both child modules and grand child modules.
     * @param string $id module ID (case-sensitive). To retrieve grand child modules,
     * use ID path relative to this module (e.g. `admin/content`).
     * @param bool $load whether to load the module if it is not yet loaded.
     * @return Module|null the module instance, `null` if the module does not exist.
     * @see hasModule()
     */
    public function getModule($id, $load = true)
    {
        if (($pos = strpos($id, '/')) !== false) {
            // sub-module
            $module = $this->getModule(substr($id, 0, $pos));

            return $module === null ? null : $module->getModule(substr($id, $pos + 1), $load);
        }

        if (isset($this->_modules[$id])) {
            if ($this->_modules[$id] instanceof self) {
                return $this->_modules[$id];
            } elseif ($load) {
                Yew::debug("Loading module: $id", __METHOD__);
                /* @var $module Module */
                $module = Yew::createObject($this->_modules[$id], [$id, $this]);
                $module::setInstance($module);
                return $this->_modules[$id] = $module;
            }
        }

        return null;
    }

    /**
     * Adds a sub-module to this module.
     * @param string $id module ID.
     * @param Module|array|null $module the sub-module to be added to this module. This can
     * be one of the following:
     *
     * - a [[Module]] object
     * - a configuration array: when [[getModule()]] is called initially, the array
     *   will be used to instantiate the sub-module
     * - `null`: the named sub-module will be removed from this module
     */
    public function setModule($id, $module)
    {
        if ($module === null) {
            unset($this->_modules[$id]);
        } else {
            $this->_modules[$id] = $module;
            if ($module instanceof self) {
                $module->module = $this;
            }
        }
    }


    /**
     * Run route
     *
     * @param $route
     * @return mixed
     * @throws InvalidConfigException
     */
    public function runRoute($route)
    {
        $controller = $this->createController($route);
        if (!empty($controller)) {
            return call_user_func([$controller[0], $controller[1]]);
        }
        return null;
    }

    /**
     * Runs a controller action specified by a route.
     * This method parses the specified route and creates the corresponding child module(s), controller and action
     * instances. It then calls [[Controller::runAction()]] to run the action with the given parameters.
     * If the route is empty, the method will use [[defaultRoute]].
     * @param string $route the route that specifies the action.
     * @param array|null $params the parameters to be passed to the action
     * @return mixed the result of the action.
     * @throws InvalidConfigException
     * @throws \ReflectionException
     * @throws \Yew\Framework\Exception\Exception
     * @throws InvalidRouteException
     */
    public function runAction(string $route, ?array $params = [])
    {
        $parts = $this->createController($route);
        if (is_array($parts)) {
            /* @var $controller Controller */
            list($controller, $actionID) = $parts;
            return $controller->runAction($actionID, $params);
        }

        return null;
    }

    /**
     * Creates a controller based on the given controller ID.
     *
     * The controller ID is relative to this module. The controller class
     * should be namespaced under [[controllerNamespace]].
     *
     * Note that this method does not check [[modules]] or [[controllerMap]].
     *
     * @param string $id the controller ID.
     * @return Controller|null the newly created controller instance, or `null` if the controller ID is invalid.
     * @throws InvalidConfigException if the controller class and its file name do not match.
     * This exception is only thrown when in debug mode.
     */
    public function createControllerByID(string $id)
    {
        $pos = strrpos($id, '/');
        if ($pos === false) {
            $prefix = '';
            $className = $id;
        } else {
            $prefix = substr($id, 0, $pos + 1);
            $className = substr($id, $pos + 1);
        }

        if ($this->isIncorrectClassNameOrPrefix($className, $prefix)) {
            return null;
        }

        $className = preg_replace_callback('%-([a-z0-9_])%i', function ($matches) {
                return ucfirst($matches[1]);
            }, ucfirst($className)) . 'Controller';
        $className = ltrim($this->controllerNamespace . '\\' . str_replace('/', '\\', $prefix) . $className, '\\');
        if (strpos($className, '-') !== false || !class_exists($className)) {
            return null;
        }

        if (is_subclass_of($className, 'Yew\Framework\Base\Controller')) {
            $controller = Yew::createObject($className, [$id, $this]);
            return get_class($controller) === $className ? $controller : null;
        } elseif (YII_DEBUG) {
            throw new InvalidConfigException('Controller class must extend from \Yew\Framework\Base\Controller.');
        }

        return null;
    }

    /**
     * Checks if class name or prefix is incorrect
     *
     * @param string $className
     * @param string $prefix
     * @return bool
     */
    private function isIncorrectClassNameOrPrefix($className, $prefix)
    {
        if (!preg_match('%^[a-z][a-z0-9\\-_]*$%', $className)) {
            return true;
        }
        if ($prefix !== '' && !preg_match('%^[a-z0-9_/]+$%i', $prefix)) {
            return true;
        }

        return false;
    }


    /**
     * This method is invoked right before an action within this module is executed.
     *
     * The method will trigger the [[EVENT_BEFORE_ACTION]] event. The return value of the method
     * will determine whether the action should continue to run.
     *
     * In case the action should not run, the request should be handled inside of the `beforeAction` code
     * by either providing the necessary output or redirecting the request. Otherwise the response will be empty.
     *
     * If you override this method, your code should look like the following:
     *
     * ```php
     * public function beforeAction($action)
     * {
     *     if (!parent::beforeAction($action)) {
     *         return false;
     *     }
     *
     *     // your custom code here
     *
     *     return true; // or false to not run the action
     * }
     * ```
     *
     * @param Action $action the action to be executed.
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction(Action $action)
    {
        $event = new ActionEvent($action);
        $this->trigger(self::EVENT_BEFORE_ACTION, $event);
        return $event->isValid;
    }

    /**
     * This method is invoked right after an action within this module is executed.
     *
     * The method will trigger the [[EVENT_AFTER_ACTION]] event. The return value of the method
     * will be used as the action return value.
     *
     * If you override this method, your code should look like the following:
     *
     * ```php
     * public function afterAction($action, $result)
     * {
     *     $result = parent::afterAction($action, $result);
     *     // your custom code here
     *     return $result;
     * }
     * ```
     *
     * @param Action $action the action just executed.
     * @param mixed $result the action return result.
     * @return mixed the processed action result.
     */
    public function afterAction(Action $action, $result)
    {
        $event = new ActionEvent($action);
        $event->result = $result;
        $this->trigger(self::EVENT_AFTER_ACTION, $event);
        return $event->result;
    }

}