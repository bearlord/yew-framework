<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Aop;

use Yew\Core\Plugins\Config\BaseConfig;
use Yew\Core\Exception\ConfigException;

class AopConfig extends BaseConfig
{
    const KEY = "aop";

    /**
     * Cache directory
     * @var string|null
     */
    protected ?string $cacheDir = null;

    /**
     * Include paths restricts the directories where aspects should be applied
     * @var string[]
     */
    protected array $includePaths = [];

    /**
     * Exclude paths restricts the directories where aspects should be applied
     * @var string[]
     */
    protected array $excludePaths = [];

    /**
     * Whether to cache files, default memory cache
     * @var bool
     */
    protected bool $fileCache = false;

    /**
     * @var OrderAspect[]
     */
    private array $aspects = [];

    /**
     * AopConfig constructor.
     * @param mixed ...$includePaths
     */
    public function __construct(...$includePaths)
    {
        parent::__construct(self::KEY);
        foreach ($includePaths as $includePath) {
            $this->addIncludePath($includePath);
        }
    }

    /**
     * @return string
     */
    public function getCacheDir(): ?string
    {
        return $this->cacheDir;
    }

    /**
     * @param string $cacheDir
     */
    public function setCacheDir(string $cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }

    /**
     * @return string[]
     */
    public function getIncludePaths(): array
    {
        return $this->includePaths;
    }

    /**
     * @param string[] $includePaths
     */
    public function setIncludePaths(array $includePaths): void
    {
        $this->includePaths = $includePaths;
    }

    /**
     * @param string $includePath
     */
    public function addIncludePath(string $includePath): void
    {
        $includePath = realpath($includePath);
        if ($includePath === false) {
            return;
        }
        $key = str_replace(realpath(ROOT_DIR), "", $includePath);
        $key = str_replace("/", ".", $key);
        $this->includePaths[$key] = $includePath;
    }

    /**
     * @param OrderAspect $param
     */
    public function addAspect(OrderAspect $param): void
    {
        $this->aspects[] = $param;
    }

    /**
     * @return OrderAspect[]
     */
    public function getAspects(): array
    {
        return $this->aspects;
    }

    /**
     * Build config
     * @throws ConfigException
     */
    public function buildConfig(): void
    {
        if (empty($this->includePaths)) {
            throw new ConfigException("includePaths cannot be empty");
        }
    }

    /**
     * @return bool
     */
    public function isFileCache(): bool
    {
        return $this->fileCache;
    }

    /**
     * @param bool $fileCache
     */
    public function setFileCache(bool $fileCache): void
    {
        $this->fileCache = $fileCache;
    }

    /**
     * @return string[]
     */
    public function getExcludePaths(): array
    {
        return $this->excludePaths;
    }

    /**
     * @param string[] $excludePaths
     */
    public function setExcludePaths(array $excludePaths): void
    {
        $this->excludePaths = $excludePaths;
    }

    /**
     * @param string $excludePath
     */
    public function addExcludePath(string $excludePath): void
    {
        $excludePath = realpath($excludePath);
        if ($excludePath === false) {
            return;
        }
        $key = str_replace(realpath(ROOT_DIR), "", $excludePath);
        $key = str_replace("/", ".", $key);
        $this->excludePaths[$key] = $excludePath;
    }
}
