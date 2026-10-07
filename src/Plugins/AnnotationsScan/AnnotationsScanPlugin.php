<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AnnotationsScan;

use DI\DependencyException;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use Yew\Core\Context\Context;
use Yew\Core\Exception;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Plugin\PluginInterfaceManager;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\AnnotationsScan\Annotation\Component;
use Yew\Plugins\AnnotationsScan\Tokenizer\Tokenizer;
use Yew\Plugins\Aop\AopPlugin;
use ReflectionClass;
use ReflectionException;

class AnnotationsScanPlugin extends AbstractPlugin
{
    use GetLogger;

    /**
     * @var AnnotationsScanConfig|null
     */
    private ?AnnotationsScanConfig $annotationsScanConfig;

    /**
     * @var CachedReader
     */
    private CachedReader $cacheReader;
    /**
     * @var ScanClass
     */
    private ScanClass $scanClass;

    /**
     * @param AnnotationsScanConfig|null $annotationsScanConfig
     */
    public function __construct(?AnnotationsScanConfig $annotationsScanConfig = null)
    {
        parent::__construct();
        if ($annotationsScanConfig == null) {
            $annotationsScanConfig = new AnnotationsScanConfig();
        }
        $this->annotationsScanConfig = $annotationsScanConfig;

        $this->atAfter(AopPlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return void
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlugin(new AopPlugin());
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return "AnnotationsScan";
    }

    /**
     * Scan PHP
     *
     * @param string $dir
     * @param null $files
     * @return array|null
     */
    private function scanPhp(string $dir, &$files = null): ?array
    {
        if ($files == null) {
            $files = array();
        }
        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != "." && $file != "..") {
                        if (is_dir($dir . "/" . $file)) {
                            $this->scanPhp($dir . "/" . $file, $files);
                        } else {
                            if (pathinfo($file, PATHINFO_EXTENSION) == "php") {
                                $files[] = $dir . "/" . $file;
                            }
                        }
                    }
                }
                closedir($handle);
                return $files;
            }
        } else {
            return $files;
        }
        return null;
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @return void
     */
    public function beforeServerStart(Context $context)
    {
    }

    /**
     * Get fully qualified class name from file content in PHP
     *
     * @param string $pathToFile
     * @return mixed|string|null
     */
    public function getClassFromFile(string $pathToFile)
    {
        return Tokenizer::getClassFromFile($pathToFile);
    }

    /**
     * @param Context $context
     * @return void
     * @throws Exception\ConfigException
     * @throws Exception\Exception
     * @throws ReflectionException
     */
    public function beforeProcessStart(Context $context)
    {
        //Add src directory by default
        $this->annotationsScanConfig->addIncludePath(Server::$instance->getServerConfig()->getSrcDir());

        $this->annotationsScanConfig->merge();
        if ($this->annotationsScanConfig->isFileCache()) {
            $cache = new FilesystemCache(
                Server::$instance->getServerConfig()->getCacheDir() . DIRECTORY_SEPARATOR . "_annotations_scan" . DIRECTORY_SEPARATOR,
                ".annotations.cache");
        } else {
            $cache = new ArrayCache();
        }
        $this->cacheReader = new CachedReader(new AnnotationReader(), $cache);
        $this->scanClass = new ScanClass($this->cacheReader);
        $this->setToDIContainer(CachedReader::class, $this->cacheReader);
        $this->setToDIContainer(ScanClass::class, $this->scanClass);

        $paths = array_unique($this->annotationsScanConfig->getIncludePaths());
        foreach ($paths as $path) {
            $files = $this->scanPhp($path);
            foreach ($files as $file) {
                $class = $this->getClassFromFile($file);
                if (empty($class)) {
                    continue;
                }

                if (!class_exists($class) && !interface_exists($class)) {
                    continue;
                }

                $reflectionClass = new ReflectionClass($class);

                // A class is scannable only when it carries a Component (or any
                // Component-subclass) annotation. Doctrine's getClassAnnotation
                // matches by `instanceof`, so @RestController/@Controller pass too;
                // getClassAndInterfaceAnnotation additionally covers the same check
                // for PHP 8 attributes (#[RestController(...)]).
                if ($this->scanClass->getClassAndInterfaceAnnotation($reflectionClass, Component::class) === null) {
                    continue;
                }

                $this->scanClassAnnotations($reflectionClass);
                foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                    $this->scanMethodAnnotations($reflectionClass, $reflectionMethod);
                }
            }
        }
        $this->ready();
    }

    /**
     * Scan every annotation (docblock + PHP 8 attribute) declared on a class:
     * the class itself, the interfaces it implements, and class-level attributes.
     */
    private function scanClassAnnotations(ReflectionClass $reflectionClass): void
    {
        // Docblock annotations declared directly on the class.
        foreach ($this->cacheReader->getClassAnnotations($reflectionClass) as $annotation) {
            $this->collectClass($reflectionClass, $annotation);
        }

        // Docblock annotations declared on the interfaces the class implements.
        foreach ($reflectionClass->getInterfaces() as $reflectionInterface) {
            foreach ($this->cacheReader->getClassAnnotations($reflectionInterface) as $annotation) {
                $this->collectClass($reflectionClass, $annotation);
            }
        }

        // PHP 8 attributes declared on the class.
        $this->collectAttributes(
            $reflectionClass,
            fn(object $annotation) => $this->collectClass($reflectionClass, $annotation),
            $reflectionClass->getName()
        );
    }

    /**
     * Scan every annotation (docblock + PHP 8 attribute) declared on a single
     * method: from the interfaces it originates in, the method itself, and
     * method-level attributes.
     */
    private function scanMethodAnnotations(ReflectionClass $reflectionClass, \ReflectionMethod $reflectionMethod): void
    {
        $scanReflectionMethod = new ScanReflectionMethod($reflectionClass, $reflectionMethod);

        // Docblock annotations declared on the method where it appears in an interface.
        foreach ($reflectionMethod->getDeclaringClass()->getInterfaces() as $reflectionInterface) {
            try {
                $reflectionInterfaceMethod = $reflectionInterface->getMethod($reflectionMethod->getName());
            } catch (\Throwable $e) {
                $reflectionInterfaceMethod = null;
            }
            if ($reflectionInterfaceMethod !== null) {
                foreach ($this->cacheReader->getMethodAnnotations($reflectionInterfaceMethod) as $annotation) {
                    $this->collectMethod($scanReflectionMethod, $annotation);
                }
            }
        }

        // Docblock annotations declared directly on the method.
        foreach ($this->cacheReader->getMethodAnnotations($reflectionMethod) as $annotation) {
            $this->collectMethod($scanReflectionMethod, $annotation);
        }

        // PHP 8 attributes declared on the method.
        $this->collectAttributes(
            $reflectionMethod,
            fn(object $annotation) => $this->collectMethod($scanReflectionMethod, $annotation),
            $reflectionClass->getName() . '::' . $reflectionMethod->getName()
        );
    }

    /**
     * Collect PHP 8 attributes from a class or method reflector, instantiating each
     * one via ScanClass::instantiateAttribute and handing it to $collector.
     * A malformed attribute is skipped with a warning instead of aborting the scan.
     */
    private function collectAttributes(
        ReflectionClass|\ReflectionMethod $reflector,
        callable $collector,
        string $targetName
    ): void {
        foreach ($reflector->getAttributes(Annotation::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            try {
                $collector(ScanClass::instantiateAttribute($attribute));
            } catch (\Throwable $e) {
                $this->warning(sprintf(
                    'Skip attribute %s on %s: %s',
                    $attribute->getName(),
                    $targetName,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Register a class-level annotation together with its parent-annotation chain.
     */
    private function collectClass(ReflectionClass $reflectionClass, object $annotation): void
    {
        $this->scanClass->addAnnotationClass(get_class($annotation), $reflectionClass);
        $parent = get_parent_class($annotation);
        if ($parent !== false && $parent !== Annotation::class) {
            $this->scanClass->addAnnotationClass($parent, $reflectionClass);
        }
    }

    /**
     * Register a method-level annotation together with its parent-annotation chain.
     */
    private function collectMethod(ScanReflectionMethod $scanReflectionMethod, object $annotation): void
    {
        $this->scanClass->addAnnotationMethod(get_class($annotation), $scanReflectionMethod);
        $parent = get_parent_class($annotation);
        if ($parent !== false && $parent !== Annotation::class) {
            $this->scanClass->addAnnotationMethod($parent, $scanReflectionMethod);
        }
    }
}
