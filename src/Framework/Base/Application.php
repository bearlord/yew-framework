<?php
/**
 * ESD framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Framework\Base;

use DI\Container;
use Yew\Core\DI\DI;
use Yew\Coroutine\Server\Server;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Exception\InvalidParamException;
use Yew\Nikic\FastRoute\Dispatcher;
use Yew\Plugins\Route\RoutePlugin;
use Yew\Plugins\Session\HttpSession;
use Yew\Framework\Di\ServiceLocator;
use Yew\Framework\Db\Connection;
use Yew\Framework\Plugin\Pdo\PdoPools;
use Yew\Framework\Plugin\Pdo\PdoPool;
use Yew\Yew;

/**
 * Class Application
 * @package \Yew\Framework\Base
 * @property \Yew\Core\Server\Beans\Request $request The request component. This property is read-only.
 * @property \Yew\Core\Server\Beans\Response $response The response component. This property is read-only.
 * @property \ESD\Plugins\Session\HttpSession $session The session component. This property is read-only.
 * @property \Yew\Framework\Web\User $user The user component. This property is read-only.
 * @property \Yew\Framework\Caching\Cache $cache The cache application component. Null if the component is not enabled.
 * @property \Yew\Framework\Base\Security $security The security application component. This property is read-only.
 * @property \Yew\Framework\I18n\Formatter $formatter
 */
class Application extends ServiceLocator
{
    /**
     * @var string the charset currently used for the application.
     */
    public string $charset = 'UTF-8';
    /**
     * @var string the language that is meant to be used for end users. It is recommended that you
     * use [IETF language tags](http://en.wikipedia.org/wiki/IETF_language_tag). For example, `en` stands
     * for English, while `en-US` stands for English (United States).
     * @see sourceLanguage
     */
    public string $language = 'en-US';

    /**
     * @var string the language that the application is written in. This mainly refers to
     * the language that the messages and view files are written in.
     * @see language
     */
    public string $sourceLanguage = 'en-US';

    /**
     * @var string Default time zone
     */
    public string $timeZone = 'Asia/Shanghai';

    /**
     * @var string Cookie validation key
     */
    public string $cookieValidationKey = 'yew';

    /**
     * @var string the root directory of the application.
     */
    private string $_basePath;

    /**
     * @var Application[]
     */
    private static array $_instances = [];

    /**
     * Application constructor.
     */
    public function __construct()
    {
        Yew::$app = $this;
        $this->preInit();
    }

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
     * Prepare init
     */
    public function preInit()
    {
        $config = Server::$instance->getConfigContext()->get('yii');

        //Set base path
        $srcDir = Server::$instance->getServerConfig()->getSrcDir();
        $this->setBasePath($srcDir);

        //Set vendor path
        $vendorPath = realpath(dirname($srcDir) . '/vendor');
        $this->setVendorPath($vendorPath);

        //Set web path
        if (Server::$instance->getServerConfig()->isEnableStaticHandler()) {
            $documentRoot = Server::$instance->getServerConfig()->getDocumentRoot();
            if (empty($documentRoot)) {
                $documentRoot = realpath(dirname($srcDir) . '/web');
            }
            $this->setWebPath(Server::$instance->getServerConfig()->getDocumentRoot());
        }

		// set "@runtime"
        $this->getRuntimePath();

        //Set language
        if (!empty($config['language'])) {
            $this->setLanguage($config['language']);
            $this->setContextLanguage($config['language']);
        }

        if (!empty($config['timezone'])) {
            $this->settimeZone($config['timezone']);
            $this->setContextTimeZone($config['timezone']);
        }

        //Merge core components with custom components
        $newConfig = $config;
        unset($newConfig['db']);

        foreach ($this->coreComponents() as $id => $component) {
            if (!isset($newConfig['components'][$id])) {
                $newConfig['components'][$id] = $component;
            } elseif (is_array($newConfig['components'][$id]) && !isset($newConfig['components'][$id]['class'])) {
                $newConfig['components'][$id]['class'] = $component['class'];
            }
        }

        $this->setComponents($newConfig['components']);

        //Instance log component, and crete object Yew\Framework\Log\Logger, set property as Logger::flushInterval, logger::traceLevel.
        //If Yew\Framework\Log\Logger is created, it can be stored in container. the next time to be created, it will return
        //the stored object that kept the defined properties.
        //If don't this, Yew\Framework\Log\Logger would not be created, 'flushInterval' and 'traceLevel' would not be set customize value
        //but default value.
        $this->getLog();

        unset($newConfig);
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
     * @throws InvalidParamException if the directory does not exist.
     */
    public function setBasePath(string $path)
    {
        $path = Yew::getAlias($path);
        $p = strncmp($path, 'phar://', 7) === 0 ? $path : realpath($path);
        if ($p !== false && is_dir($p)) {
            $this->_basePath = $p;
        } else {
            throw new InvalidParamException("The directory does not exist: $path");
        }
        Yew::setAlias('@app', $this->getBasePath());
        Yew::setAlias('@App', $this->getBasePath());
    }

    private $_vendorPath;

    /**
     * Returns the directory that stores vendor files.
     * @return string the directory that stores vendor files.
     * Defaults to "vendor" directory under [[basePath]].
     */
    public function getVendorPath(): string
    {
        if ($this->_vendorPath === null) {
            $this->setVendorPath($this->getBasePath() . DIRECTORY_SEPARATOR . 'vendor');
        }

        return $this->_vendorPath;
    }

    /**
     * Sets the directory that stores vendor files.
     * @param string $path the directory that stores vendor files.
     */
    public function setVendorPath(string $path)
    {
        $this->_vendorPath = Yew::getAlias($path);
        Yew::setAlias('@vendor', $this->_vendorPath);
        Yew::setAlias('@bower', $this->_vendorPath . DIRECTORY_SEPARATOR . 'bower-asset');
        Yew::setAlias('@npm', $this->_vendorPath . DIRECTORY_SEPARATOR . 'npm-asset');
    }

    /**
     * Sets the web and webroot path
     * @param string $path
     */
    public function setWebPath(string $path)
    {
        Yew::setAlias('@webroot', $path);
        Yew::setAlias('@web', '/');
    }

    private $_runtimePath;

    /**
     * Returns the directory that stores runtime files.
     * @return string the directory that stores runtime files.
     * Defaults to the "runtime" subdirectory under [[basePath]].
     */
    public function getRuntimePath(): string
    {
        if ($this->_runtimePath === null) {
            $this->setRuntimePath(realpath(dirname($this->getBasePath())) . DIRECTORY_SEPARATOR . 'bin/runtime');
        }

        return $this->_runtimePath;
    }

    /**
     * Sets the directory that stores runtime files.
     * @param string $path the directory that stores runtime files.
     */
    public function setRuntimePath(string $path)
    {
        $this->_runtimePath = Yew::getAlias($path);
        Yew::setAlias('@runtime', $this->_runtimePath);
    }

    /**
     * @param string|null $name
     * @return mixed
     * @throws \Yew\Framework\Base\InvalidConfigException
     * @throws \Yew\Framework\Db\Exception
     */
    public function getDb(?string $name = "default")
    {
        $subname = "";
        if (strpos($name, ".") > 0) {
            list($name, $subname) = explode(".", $name, 2);
        }

        switch ($subname) {
            case "slave":
            case "master":
                $_configKey = sprintf("yii.db.%s.%ss", $name, $subname);
                $_configs = Server::$instance->getConfigContext()->get($_configKey);
                if (empty($_configs)) {
                    $poolKey = $name;
                    $contextKey = sprintf("Pdo:%s", $name);
                } else {
                    $_randKey = array_rand($_configs);

                    $poolKey = sprintf("%s.%s.%s", $name, $subname, $_randKey);
                    $contextKey = sprintf("Pdo:{$name}%s.%s.%s", $name, $subname, $_randKey);
                }
                break;

            default:
                $poolKey = $name;
                $contextKey = sprintf("Pdo:%s", $name);
                break;
        }

        $db = getContextValue($contextKey);

        if ($db == null) {
            /** @var PdoPools $pdoPools */
            $pdoPools = getDeepContextValueByClassName(PdoPools::class);
            if (!empty($pdoPools)) {
                /** @var \Yew\Framework\Plugin\Pdo\PdoPool $pool */
                $pool = $pdoPools->getPool($poolKey);
                if ($pool == null) {
                    Server::$instance->getLog()->error("No Pdo connection pool named {$poolKey} was found");
                    throw new \PDOException("No Pdo connection pool named {$poolKey} was found");
                }
                try {
                    $db = $pool->db();
                    if (empty($db)) {
                        Server::$instance->getLog()->error("Empty db, get db once.");
                        return $this->getDbOnce($name);
                    }
                    return $db;
                } catch (\Exception $e) {
                    Server::$instance->getLog()->error($e);
                }
            } else {
                return $this->getDbOnce($name);
            }
        } else {
            return $db;
        }
    }

    /**
     * @param $name
     * @return Connection|null
     * @throws \Yew\Framework\Exception\InvalidConfigException
     */
    public function getDbOnce($name): ?Connection
    {
        $contextKey = sprintf("Pdo:%s", $name);
        $db = getContextValue($contextKey);
        if (!empty($db)) {
            return $db;
        }

        $_configKey = sprintf("yii.db.%s", $name);
        $_config = Server::$instance->getConfigContext()->get($_configKey);
        $db = Yew::createObject([
            'class' => Connection::class,
            'poolName' => $name,
            'dsn' => $_config['dsn'],
            'username' => $_config['username'],
            'password' => $_config['password'],
            'charset' => $_config['charset'] ?? 'utf8',
            'tablePrefix' => $_config['tablePrefix'],
            'enableSchemaCache' => $_config['enableSchemaCache'],
            'schemaCacheDuration' => $_config['schemaCacheDuration'],
            'schemaCache' => $_config['schemaCache'],
        ]);
        $db->open();
        setContextValue($contextKey, $db);

        return $db;
    }

    /**
     * Returns the log dispatcher component.
     * @return \Yew\Framework\Log\Dispatcher the log dispatcher application component.
     * @throws InvalidConfigException
     */
    public function getLog(): \Yew\Framework\Log\Dispatcher
    {
        return $this->get('log');
    }

    /**
     * Returns the error handler component.
     * @return ErrorHandler the error handler application component.
     * @throws InvalidConfigException
     */
    public function getErrorHandler(): ErrorHandler
    {
        return $this->get('errorHandler');
    }

    /**
     * Returns the request component.
     * @return \Yew\Core\Server\Beans\Request the request component.
     */
    public function getRequest(): Request
    {
        return getDeepContextValueByClassName(Request::class);
    }

    /**
     * Returns the response component.
     * @return \Yew\Core\Server\Beans\Response the response component.
     */
    public function getResponse(): Response
    {
        return getDeepContextValueByClassName(Response::class);
    }

    /**
     * Returns the formatter component.
     * @return \Yew\Framework\I18n\Formatter the formatter application component.
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getFormatter(): \Yew\Framework\I18n\Formatter
    {
        return $this->get('formatter');
    }

    /**
     * Returns the internationalization (i18n) component
     * @return \Yew\Framework\I18n\I18N the internationalization application component.
     * @throws InvalidConfigException
     */
    public function getI18n(): \Yew\Framework\I18n\I18N
    {
        return $this->get('i18n');
    }

    /**
     * Returns the cache component.
     * @return \Yew\Framework\Caching\Cache the cache application component. Null if the component is not enabled.
     * @throws InvalidConfigException
     */
    public function getCache(): \Yew\Framework\Caching\Cache
    {
        return $this->get('cache');
    }

    /**
     * Returns the URL manager for this application.
     * @return \Yew\Framework\Web\UrlManager the URL manager for this application.
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getUrlManager(): \Yew\Framework\Web\UrlManager
    {
        return $this->get('urlManager');
    }


    /**
     * Returns the asset manager.
     * @return \Yew\Framework\Web\AssetManager the asset manager application component.
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getAssetManager(): \Yew\Framework\Web\AssetManager
    {
        return $this->get('assetManager');
    }

    /**
     * Returns the security component.
     * @return \Yew\Framework\Base\Security the security application component.
     * @throws InvalidConfigException
     */
    public function getSecurity(): \Yew\Framework\Base\Security
    {
        return $this->get('security');
    }

    /**
     * Returns the view object.
     * @return View|\Yew\Framework\Web\View the view application component that is used to render various view files.
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getView()
    {
        return $this->get('view');
    }

    /**
     * Returns the session component.
     * @return HttpSession the session component.
     */
    public function getSession(): HttpSession
    {
        $session = getDeepContextValueByClassName(HttpSession::class);
        if ($session == null) {
            $session = new HttpSession();
        }
        return $session;
    }

    /**
     * Returns the dynamic language
     *
     * @return string
     */
    public function getLanguage(): string
    {
        /** @var Request $request */
        $request = getDeepContextValueByClassName(Request::class);

        $inputLanguage = $cookieLanguage = '';
        if (!empty($request)) {
            /** @var string $inputLanguage */
            $inputLanguage = $request->input('language');
            /** @var string $cookieLanguage */
            $cookieLanguage = $request->cookie('language');
        }

        if (!empty($inputLanguage)) {
            $lang = $inputLanguage;
        } else if (!empty($cookieLanguage)) {
            $lang = $cookieLanguage;
        } else {
            $lang = $this->language;
        }
        return $lang;
    }

    /**
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        $this->language = $language;

        setContextValue("language", $language);
    }

    /**
     * @param string $key
     * @return string
     */
    public function getContextLanguage(): string
    {
        return getDeepContextValue("language");
    }

    /**
     * @param string $language
     * @return void
     */
    public function setContextLanguage(string $language): void
    {
        setContextValue("language", $language);
    }

    /**
     * @return string
     */
    public function getTimeZone(): string
    {
        return $this->timeZone;
    }

    /**
     * @param string $timeZone
     * @return void
     */
    public function setTimeZone(string $timeZone): void
    {
        $this->timeZone = $timeZone;
    }

    /**
     * @return string
     */
    public function getContextTimeZone(): string
    {
        return getDeepContextValue("timeZone");
    }

    /**
     * @param string $timeZone
     * @return void
     */
    public function setContextTimeZone(string $timeZone): void
    {
        setContextValue("timeZone", $timeZone);
    }

    /**
     * @return \Yew\Framework\Mongodb\Connection|mixed
     * @throws \Yew\Framework\Base\InvalidConfigException
     * @throws \Yew\Framework\Db\Exception
     */
    public function getMongodb()
    {
        $poolKey = "default";
        $contextKey = "Mongodb:default";

        $db = getContextValue($contextKey);

        if ($db == null) {
            /** @var MongodbPools $pdoPools */
            $pdoPools = getDeepContextValueByClassName(MongodbPools::class);
            if (!empty($pdoPools)) {
                $pool = $pdoPools->getPool($poolKey);
                if ($pool == null) {
                    throw new \PDOException("No Pdo connection pool named {$poolKey} was found");
                }
                return $pool->db();
            } else {
                return $this->getDbOnce();
            }

        } else {
            return $db;
        }
    }

    /**
     * Get db once
     * @return \Yew\Framework\Mongodb\Connection|object|null
     * @throws \Yew\Framework\Mongodb\Exception|\Yew\Framework\Base\InvalidConfigException
     */
    public function getMongodbOnce(): ?\Yew\Framework\Mongodb\Connection
    {
        $config = Server::$instance->getConfigContext()->get("yii.db.mongodb");
        $db = Yew::createObject([
            'class' => \Yew\Framework\Mongodb\Connection::class,
            'dsn' => $config['dsn'],
            'username' => $config['username'],
            'password' => $config['password'],
            'options' => $config['options'],
            'tablePrefix' => $config['tablePrefix'],
            'enableSchemaCache' => $config['enableSchemaCache'],
            'schemaCacheDuration' => $config['schemaCacheDuration'],
            'schemaCache' => $config['schemaCache'],
        ]);

        $db->open();
        return $db;
    }

    /**
     * @param $route
     * @return array
     * @throws InvalidConfigException
     */
    public function createController($route): array
    {
        $route = "/" . trim($route, "/");
        if (strpos($route, '/') !== false) {
            list($id, $_route) = explode('/', $route, 2);
        } else {
            $id = $route;
            $_route = '';
        }

        $method = $this->request->server('request_method');
        $port = $this->request->server('server_port');
        $routeInfo = EasyRoutePlugin::$instance->getDispatcher()->dispatch($port . ":" . $method, $route);

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
        return [];
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
     * @throws \Yew\Framework\Base\Exception
     * @throws \Yew\Framework\Base\InvalidConfigException
     * @throws \Yew\Framework\Base\InvalidRouteException if the requested route cannot be resolved into an action successfully.
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
     * Returns the configuration of core application components.
     * @return array
     * @see set()
     */
    public function coreComponents(): array
    {
        return [
            'formatter' => ['class' => '\Yew\Framework\I18n\Formatter'],
            'i18n' => ['class' => 'Yew\Framework\I18n\I18N'],
            'log' => ['class' => 'Yew\Framework\Log\Dispatcher'],
            'security' => ['class' => 'Yew\Framework\Base\Security'],
            'errorHandler' => ['class' => 'Yew\Framework\Base\ErrorHandler'],
            'view' => ['class' => 'Yew\Framework\Web\View'],
            'urlManager' => ['class' => 'Yew\Framework\Web\UrlManager'],
            'assetManager' => ['class' => 'Yew\Framework\Web\AssetManager']
        ];
    }
}
