<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Aop;

use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Yew\Goaop\Instrument\FileSystem\Enumerator;
use Yew\Goaop\Instrument\PathResolver;
use Yew\Goaop\Instrument\Transformer\FilterInjectorTransformer;
use Yew\Goaop\Core\AspectContainer;

class AopComposerLoader extends \Yew\Goaop\Instrument\ClassLoading\AopComposerLoader
{
    /** @var bool */
    private static bool $wasInitialized = false;

    /**
     * AopComposerLoader constructor.
     * @param ClassLoader $original
     * @param AspectContainer $container
     * @param array $options
     */
    public function __construct(ClassLoader $original, AspectContainer $container, array $options = [])
    {
        $this->options = $options;
        $this->original = $original;

        $prefixes = $original->getPrefixes();
        $excludePaths = $options['excludePaths'];

        if (!empty($prefixes)) {
            // Let's exclude core dependencies from that list
            if (isset($prefixes['Dissect'])) {
                $excludePaths[] = $prefixes['Dissect'][0];
            }
            if (isset($prefixes['Doctrine\\Common\\Annotations\\'])) {
                $excludePaths[] = substr($prefixes['Doctrine\\Common\\Annotations\\'][0], 0, -16);
            }
        }

        $fileEnumerator = new Enumerator($options['appDir'], $options['includePaths'], $excludePaths);
        $this->fileEnumerator = $fileEnumerator;
    }

    /**
     * @return array|null
     */
    public function getIncludePath(): ?array
    {
        return $this->options['includePaths'];
    }

    /**
     * @param array $options
     * @param AspectContainer $container
     * @return bool
     */
    public static function init(array $options, AspectContainer $container): bool
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;

            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $originalLoader = $loader[0];

                // Configure library loader for doctrine annotation loader
                AnnotationRegistry::registerLoader(function ($class) use ($originalLoader) {
                    $originalLoader->loadClass($class);

                    return class_exists($class, false);
                });

                $loader[0] = new AopComposerLoader($loader[0], $container, $options);
                self::$wasInitialized = true;
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        return self::$wasInitialized;
    }

    /**
     * @return bool
     */
    public static function wasInitialized(): bool
    {
        return self::$wasInitialized;
    }

    /**
     * @param string $class
     * @return void
     * @throws \Exception
     */
    public function loadClass($class): void
    {
        //File operations must close the global RuntimeCoroutine
        enableRuntimeCoroutine(false);
        $file = $this->findFile($class);

        if ($file === false) {
            return;
        }

        if (PHP_MAJOR_VERSION >= 8) {
            $this->loadClassPHP8($class, $file);
            return;
        }

        $this->loadClassPHP7($class, $file);

    }

    /**
     * @param string $class
     * @param string $file
     * @return void
     * @throws \Exception
     */
    protected function loadClassPHP7(string $class, string $file)
    {
        if (strpos($class, "App\\") !== false) {
            include $file;
            return;
        }

        include $file;
    }

    /**
     * @param string $class
     * @param string $file
     * @return void
     */
    protected function loadClassPHP8(string $class, string $file)
    {
        include_once $file;
    }


    /**
     * @param string $class
     * @return false|string
     */
    public function findFile($class)
    {
        static $isAllowedFilter = null;
        if (!$isAllowedFilter) {
            $isAllowedFilter = $this->fileEnumerator->getFilter();
        }

        $file = $this->original->findFile($class);

        if ($file !== false) {
            $file = PathResolver::realpath($file) ?: $file;
            if ($isAllowedFilter(new \SplFileInfo($file))) {
                // can be optimized here with $cacheState even for debug mode, but no needed right now
                $file = FilterInjectorTransformer::rewrite($file);
            }
        }

        return $file;
    }



}
