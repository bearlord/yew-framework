<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\DI;

use DI\Container;
use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;

/**
 * Class DI
 * @package Yew\Core\DI
 */
class DI
{
    public static array $definitions = [];

    /**
     * @var DI|null
     */
    private static ?DI $instance = null;

    /**
     * @var Container
     */
    private Container $container;

    /**
     * DI constructor.
     */
    public function __construct()
    {
        $builder = new ContainerBuilder();

        /*
        $cacheProxiesDir = ROOT_DIR . '/bin/cache/proxies';
         if (!file_exists($cacheProxiesDir)) {
             mkdir($cacheProxiesDir, 0777, true);
         }
         $cacheDir = ROOT_DIR . "/bin/cache/di";
         if (!file_exists($cacheDir)) {
             mkdir($cacheDir, 0777, true);
         }
         $builder->enableCompilation($cacheDir);
         $builder->writeProxiesToFile(true, $cacheProxiesDir);
        */

        $builder->addDefinitions(self::$definitions);
        $builder->useAnnotations(true);

        try {
            $this->container = $builder->build();
        } catch (Exception $exception) {
            //do nothing
        }
    }

    /**
     * @return DI
     */
    public static function getInstance(): DI
    {
        if (self::$instance == null) {
            self::$instance = new DI();
        }
        return self::$instance;
    }

    /**
     * Get container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get
     *
     * @param string $name
     * @param array|null $params
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function get(string $name, ?array $params = [])
    {
        $result = $this->getContainer()->get($name);
        if ($result instanceof Factory) {
            $result = $result->create($params);
        }
        return $result;
    }

    /**
     * Set
     *
     * @param string $name
     * @param $value
     */
    public function set(string $name, $value)
    {
        $this->container->set($name, $value);
    }

    /**
     * @param string $name
     * @param array $value
     * @return mixed|string
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function make(string $name, array $value = [])
    {
        return $this->container->make($name, $value);
    }
}
