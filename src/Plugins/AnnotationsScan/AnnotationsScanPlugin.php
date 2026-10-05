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

                if (interface_exists($class) || class_exists($class)) {
                    $reflectionClass = new ReflectionClass($class);
                    $has = $this->cacheReader->getClassAnnotation($reflectionClass, Component::class);
                    if ($has == null) {
                        continue;
                    }

                    //Only those that inherit Component annotations will be scanned
                    //View annotations on classes
                    foreach ($this->cacheReader->getClassAnnotations($reflectionClass) as $annotation) {
                        $this->collectClass($reflectionClass, $annotation);
                    }

                    //Add annotations in class interfaces
                    foreach ($reflectionClass->getInterfaces() as $reflectionInterface) {
                        foreach ($this->cacheReader->getClassAnnotations($reflectionInterface) as $annotation) {
                            $this->collectClass($reflectionClass, $annotation);
                        }
                    }

                    // PHP 8 attribute support: collect class-level attributes as well
                    foreach ($reflectionClass->getAttributes(Annotation::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                        $this->collectClass($reflectionClass, $attribute->newInstance());
                    }

                    //View method annotations
                    foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                        $scanReflectionMethod = new ScanReflectionMethod($reflectionClass, $reflectionMethod);

                        foreach ($reflectionMethod->getDeclaringClass()->getInterfaces() as $reflectionInterface) {
                            try {
                                $reflectionInterfaceMethod = $reflectionInterface->getMethod($reflectionMethod->getName());
                            } catch (\Throwable $e) {
                                $reflectionInterfaceMethod = null;
                            }
                            if ($reflectionInterfaceMethod != null) {
                                foreach ($this->cacheReader->getMethodAnnotations($reflectionInterfaceMethod) as $annotation) {
                                    $this->collectMethod($scanReflectionMethod, $annotation);
                                }
                            }
                        }

                        foreach ($this->cacheReader->getMethodAnnotations($reflectionMethod) as $annotation) {
                            $this->collectMethod($scanReflectionMethod, $annotation);
                        }

                        // PHP 8 attribute support: collect method-level attributes as well
                        foreach ($reflectionMethod->getAttributes(Annotation::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                            $this->collectMethod($scanReflectionMethod, $attribute->newInstance());
                        }
                    }
                }
            }
        }
        $this->ready();
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
