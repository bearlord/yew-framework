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
use Yew\Framework\Helpers\StringHelper;
use Yew\Yew;
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
                Server::$instance->getServerConfig()->getCacheDir() . DIRECTORY_SEPARATOR . '_annotations_scan' . DIRECTORY_SEPARATOR,
                '.annotations.cache');
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
                    $annotations = $this->cacheReader->getClassAnnotations($reflectionClass);
                    foreach ($annotations as $annotation) {
                        $annotationClass = get_class($annotation);
                        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                            $_message = sprintf("@%s in %s",
                                StringHelper::basename($annotationClass),
                                $class
                            );
                            $this->debug($_message);
                        }

                        $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                        $annotationClass = get_parent_class($annotation);
                        if ($annotationClass != Annotation::class) {
                            if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                $_message = sprintf("@%s in %s",
                                    StringHelper::basename($annotationClass),
                                    $class
                                );
                                $this->debug($_message);
                            }

                            $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                        }
                    }

                    //Add annotations in class interfaces
                    $reflectionInterfaces = $reflectionClass->getInterfaces();
                    foreach ($reflectionInterfaces as $reflectionInterface) {
                        $annotations = $this->cacheReader->getClassAnnotations($reflectionInterface);
                        foreach ($annotations as $annotation) {
                            $annotationClass = get_class($annotation);
                            if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                $_message = sprintf("@%s in %s",
                                    StringHelper::basename($annotationClass),
                                    $class
                                );
                                $this->debug($_message);
                            }

                            $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                            $annotationClass = get_parent_class($annotation);
                            if ($annotationClass != Annotation::class) {
                                if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                    $_message = sprintf("@%s in %s",
                                        StringHelper::basename($annotationClass),
                                        $class
                                    );
                                    $this->debug($_message);
                                }
                                $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                            }
                        }
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
                                $annotations = $this->cacheReader->getMethodAnnotations($reflectionInterfaceMethod);
                                foreach ($annotations as $annotation) {
                                    $annotationClass = get_class($annotation);
                                    if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                        $_message = sprintf("%s in %s::%s",
                                            StringHelper::basename($annotationClass),
                                            $class,
                                            $reflectionMethod->name
                                        );
                                        $this->debug($_message);
                                    }
                                    $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                                    $annotationClass = get_parent_class($annotation);
                                    if ($annotationClass != Annotation::class) {
                                        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                            $_message = sprintf("%s in %s::%s",
                                                StringHelper::basename($annotationClass),
                                                $class,
                                                $reflectionMethod->name
                                            );
                                            $this->debug($_message);
                                        }
                                        $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                                    }
                                }
                            }
                        }

                        $annotations = $this->cacheReader->getMethodAnnotations($reflectionMethod);
                        foreach ($annotations as $annotation) {
                            $annotationClass = get_class($annotation);
                            if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                $_message = sprintf("%s in %s::%s",
                                    StringHelper::basename($annotationClass),
                                    $class,
                                    $reflectionMethod->name
                                );
                                $this->debug($_message);
                            }

                            $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                            $annotationClass = get_parent_class($annotation);
                            if ($annotationClass != Annotation::class) {
                                if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                    $_message = sprintf("%s in %s::%s",
                                        StringHelper::basename($annotationClass),
                                        $class,
                                        $reflectionMethod->name
                                    );
                                    $this->debug($_message);
                                }
                                $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                            }
                        }
                    }
                }
            }

        }
        $this->ready();
    }
}
