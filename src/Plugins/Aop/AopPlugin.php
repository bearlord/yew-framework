<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Aop;

use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\Common\Cache\ArrayCache;
use Yew\Core\Context\Context;
use Yew\Core\Exception\Exception;
use Yew\Core\Order\OrderOwnerTrait;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Core\Exception\ConfigException;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;

class AopPlugin extends AbstractPlugin
{
    use OrderOwnerTrait;
    use GetLogger;

    /**
     * @var AopConfig|null
     */
    private ?AopConfig $aopConfig;

    /**
     * @var array
     */
    private array $options;

    /** @var ApplicationAspectKernel */
    protected ApplicationAspectKernel $applicationAspectKernel;

    /**
     * AopPlugin constructor.
     * @param AopConfig|null $aopConfig
     */
    public function __construct(?AopConfig $aopConfig = null)
    {
        parent::__construct();
        if ($aopConfig == null) {
            $aopConfig = new AopConfig();
        }
        $this->aopConfig = $aopConfig;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return "Aop";
    }

    /**
     * @inheritDoc
     * @param Context $context
     * @throws ConfigException
     * @throws Exception
     * @throws \Exception
     */
    public function init(Context $context)
    {
        parent::init($context);
        //File operations must close the global RuntimeCoroutine
        enableRuntimeCoroutine(false);

        $cacheDir = $this->aopConfig->getCacheDir() ?? Server::$instance->getServerConfig()->getBinDir() . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR . "aop";
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        $this->aopConfig->merge();

        //Add src directory automatically
        $serverConfig = Server::$instance->getServerConfig();
        $this->aopConfig->addIncludePath($serverConfig->getSrcDir());
        $this->aopConfig->addIncludePath($serverConfig->getVendorDir() . "/bearlord/yew-framework/src");
        $this->aopConfig->setCacheDir($cacheDir);

        $serverConfig = Server::$instance->getServerConfig();
        //Exclude paths
        $excludePaths = Server::$instance->getConfigContext()->get("yew.aop.excludePaths");
        if (!empty($excludePaths)) {
            foreach ($excludePaths as $excludePath) {
                $this->aopConfig->addExcludePath($excludePath);
            }
        }

        $this->aopConfig->merge();

        $this->applicationAspectKernel = ApplicationAspectKernel::getInstance();
        $this->applicationAspectKernel->setConfig($this->aopConfig);
        $this->options = [
            //Use 'false' for production mode
            'debug' => $serverConfig->isDebug(),
            //Application root directory
            'appDir' => $serverConfig->getRootDir(),
            //Cache directory
            'cacheDir' => $this->aopConfig->getCacheDir(),
            //Include paths
            'includePaths' => $this->aopConfig->getIncludePaths(),
            //Exclude paths
            'excludePaths' => $this->aopConfig->getExcludePaths()
        ];
        if (!$this->aopConfig->isFileCache()) {
            $this->options['annotationCache'] = new ArrayCache();
        }

        $this->applicationAspectKernel->initContainer($this->options);
    }

    /**
     * @param Context $context
     * @return void
     * @throws DependencyException
     * @throws Exception
     * @throws NotFoundException
     */
    public function beforeServerStart(Context $context)
    {
        $serverConfig = Server::$instance->getServerConfig();
        $this->options = [
            //Use 'false' for production mode
            'debug' => $serverConfig->isDebug(),
            //Application root directory
            'appDir' => $serverConfig->getRootDir(),
            //Cache directory
            'cacheDir' => $this->aopConfig->getCacheDir(),
            //Include paths
            'includePaths' => $this->aopConfig->getIncludePaths(),
            //Exclude paths
            'excludePaths' => $this->aopConfig->getExcludePaths()
        ];

        $this->applicationAspectKernel->init($this->options);

    }

    /**
     * @param Context $context
     * @return void
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }

    /**
     * @return AopConfig
     */
    public function getAopConfig(): AopConfig
    {
        return $this->aopConfig;
    }

    /**
     * @param AopConfig $aopConfig
     */
    public function setAopConfig(AopConfig $aopConfig): void
    {
        $this->aopConfig = $aopConfig;
    }

}