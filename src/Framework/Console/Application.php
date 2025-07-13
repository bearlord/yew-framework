<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Console;

use Yew\Core\Server\Server;
use Yew\Framework\Config\ConfigFactory;
use Yew\Framework\Console\Exception\Exception;
use Yew\Framework\Console\Exception\UnknownCommandException;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Exception\InvalidRouteException;
use Yew\Framework\Base\Action;
use Yew\Framework\Base\Controller;
use Yew\Framework\Helpers\Console;
use Yew\Yew;

/**
 * @property \Yew\Core\Server\Beans\Request $request The request component. This property is read-only.
 * @property \Yew\Core\Server\Beans\Response $response The response component. This property is read-only.
 * @property \Yew\Plugins\Session\HttpSession $session The session component. This property is read-only.
 * @property \Yew\Framework\Web\User $user The user component. This property is read-only.
 * @property \Yew\Framework\Caching\Cache $cache The cache application component. Null if the component is not enabled.
 * @property \Yew\Framework\Base\Security $security The security application component. This property is read-only.
 */
class Application extends \Yew\Framework\Base\Application
{
    /**
     * The option name for specifying the application configuration file path.
     */
    const OPTION_APPCONFIG = 'appconfig';

    /**
     * @var static[] static instances in format: `[className => object]`
     */
    private static array $_instances = [];

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
     *   'account' => 'app\controllers\UserController',
     *   'article' => [
     *      'class' => 'app\controllers\PostController',
     *      'pageTitle' => 'something new',
     *   ],
     * ]
     * ```
     */
    public array $controllerMap = [];

    /**
     * @var string the namespace that controller classes are located in.
     * This namespace will be used to load controller classes by prepending it to the controller class name.
     * The default namespace is `app\controllers`.
     *
     * Please refer to the [guide about class autoloading](guide:concept-autoloading.md) for more details.
     */
    public ?string $controllerNamespace = 'App\Commands';

    /**
     * @var string the default route of this application. Defaults to 'help',
     * meaning the `help` command.
     */
    public string $defaultRoute = 'help';

    /**
     * @var bool whether to enable the commands provided by the core framework.
     * Defaults to true.
     */
    public bool $enableCoreCommands = true;

    /**
     * @var string the requested route
     */
    public string $requestedRoute;
    
    /**
     * @var Action the requested Action. If null, it means the request cannot be resolved into an action.
     */
    public Action $requestedAction;

    /**
     * @var array|null the parameters supplied to the requested action.
     */
    public ?array $requestedParams = null;


    /**
     * @var array list of components that should be run during the application [[bootstrap()|bootstrapping process]].
     *
     * Each component may be specified in one of the following formats:
     *
     * - an application component ID as specified via [[components]].
     * - a module ID as specified via [[modules]].
     * - a class name.
     * - a configuration array.
     * - a Closure
     *
     * During the bootstrapping process, each component will be instantiated. If the component class
     * implements [[BootstrapInterface]], its [[BootstrapInterface::bootstrap()|bootstrap()]] method
     * will be also be called.
     */
    public array $bootstrap = ['generator'];

    /**
     * Returns static class instance, which can be used to obtain meta information.
     * @param bool $refresh whether to re-create static instance even, if it is already cached.
     * @return static class instance.
     */
    public static function instance(?bool $refresh = false): self
    {
        $className = get_called_class();
        if ($refresh || !isset(self::$_instances[$className])) {
            $instance = new self();
            self::$_instances[$className] = $instance;
        }
        return self::$_instances[$className];
    }


    /**
     * Initialize the application.
     */
    public function init()
    {
        parent::init();

        if ($this->enableCoreCommands) {
            foreach ($this->coreCommands() as $id => $command) {
                if (!isset($this->controllerMap[$id])) {
                    $this->controllerMap[$id] = $command;
                }
            }
        }

        // ensure we have the 'help' command so that we can list the available commands
        if (!isset($this->controllerMap['help'])) {
            $this->controllerMap['help'] = 'Yew\Framework\Console\Controllers\HelpController';
        }
    }

    /**
     * @return void
     * @throws InvalidConfigException
     * @throws \Yew\Core\Exception\Exception
     */
    public function preInit()
    {
        parent::preInit();


        $_bootstrap = $this->config->get('yew.bootstrap');
        $_modules = $this->config->get('yew.modules');

        if (!empty($_bootstrap) && is_array($_bootstrap)) {
            $this->setBootstrap($_bootstrap);
        }

        if (!empty($_modules)) {
            foreach ($_modules as $id => $value) {
                if (!$this->hasModule($id) && !empty($value)) {
                    $this->setModule($id, $value);
                }
            }
        }


        if ($this->enableCoreCommands) {
            foreach ($this->coreCommands() as $id => $command) {
                if (!isset($this->controllerMap[$id])) {
                    $this->controllerMap[$id] = $command;
                }
            }
        }
    }

    /**
     * @return \Yew\Framework\Console\Request|null
     * @throws InvalidConfigException
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * @return \Yew\Framework\Console\Response|null
     * @throws InvalidConfigException
     */
    public function getResponse()
    {
        return $this->get('response');
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function run()
    {
        try {
            $response = $this->handleRequest($this->getRequest());
        } catch (\Exception $exception) {
            throw $exception;
            //return $exception->getCode();
        }
    }

    /**
     * Handles the specified request.
     * @param Request $request the request to be handled
     * @return Response the resulting response
     */
    public function handleRequest($request)
    {
        list($route, $params) = $request->resolve();

        $this->requestedRoute = $route;
        $result = $this->runAction($route, $params);
        if ($result instanceof Response) {
            return $result;
        }

        $response = $this->getResponse();
        $response->exitStatus = $result;

        return $response;
    }


    /**
     * @param string $route
     * @return array|false
     * @throws InvalidConfigException
     */
    public function createController(string $route): ?array
    {
        $_route = $route;

        // double slashes or leading/ending slashes may cause substr problem
        $route = trim($route, '/');
        if (strpos($route, '//') !== false) {
            return [];
        }


        if (strpos($route, '/') !== false) {
            list($id, $route) = explode('/', $route, 2);
        } else {
            $id = $route;
            $route = '';
        }

        // module and controller map take precedence
        if (isset($this->controllerMap[$id])) {
            $controller = Yew::createObject($this->controllerMap[$id], [$id, $this]);
            return [$controller, $route];
        }

        $module = $this->getModule($id);

        if ($module !== null) {
            return $module->createController($_route);
        }

        $controller = $this->createControllerByID($id);

        if ($controller === null && $route !== '') {
            $controller = $this->createControllerByID($id . '/' . $route);
            $route = '';
        }

        return $controller === null ? null : [$controller, $route];
    }


    /**
     * Run route
     *
     * @param $route
     * @return mixed|void
     * @throws InvalidConfigException
     */
    public function runRoute($route)
    {
        $controller = $this->createController($route);
        if (!empty($controller)) {
            return call_user_func([$controller[0], $controller[1]]);
        }
    }

    /**
     * Runs a controller action specified by a route.
     * This method parses the specified route and creates the corresponding child module(s), controller and action
     * instances. It then calls [[Controller::runAction()]] to run the action with the given parameters.
     * If the route is empty, the method will use [[defaultRoute]].
     *
     * For example, to run `public function actionTest($a, $b)` assuming that the controller has options the following
     * code should be used:
     *
     * ```php
     * \Yew::$app->runAction('controller/test', ['option' => 'value', $a, $b]);
     * ```
     *
     * @param string $route the route that specifies the action.
     * @param array|null $params the parameters to be passed to the action
     * @return int the result of the action. This can be either an exit code or Response object.
     * Exit code 0 means normal, and other values mean abnormal. Exit code of `null` is treaded as `0` as well.
     * @throws Exception if the route is invalid
     * @throws InvalidConfigException
     * @throws UnknownCommandException
     * @throws \ReflectionException
     * @throws \Yew\Framework\Exception\Exception
     */
    public function runAction(string $route, ?array $params = [])
    {
        try {
            $res = parent::runAction($route, $params);
            return is_object($res) ? $res : (int) $res;
        } catch (InvalidRouteException $e) {
            //throw new UnknownCommandException($route, $this, 0, $e);

            //$_message = "Unknown command \"$route\".";
            Console::stderr("Unknown command \"$route\"." . "\n");
        }
    }

    /**
     * Returns the configuration of core application components.
     * @see set()
     */
    public function coreComponents(): array
    {
        return array_merge(parent::coreComponents(), [
            'request' => ['class' => '\Yew\Framework\Console\Request'],
            'response' => ['class' => '\Yew\Framework\Console\Response'],
        ]);
    }

    /**
     * Returns the configuration of the built-in commands.
     * @return array the configuration of the built-in commands.
     */
    public function coreCommands(): array
    {
        return [
            'help' => 'Yew\Framework\Console\Controllers\HelpController',
            'cache' => 'Yew\Framework\Console\Controllers\CacheController',
            'migrate' => 'Yew\Framework\Console\Controllers\MigrateController'
        ];
    }
}
