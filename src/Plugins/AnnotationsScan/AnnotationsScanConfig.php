<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\AnnotationsScan;

use Yew\Core\Plugins\Config\BaseConfig;


class AnnotationsScanConfig extends BaseConfig
{
    const KEY = "scan";

    /**
     * @var array
     */
    protected $includePaths = [];

    /**
     * Whether to cache files, default memory cache
     * @var bool
     */
    protected $fileCache = false;

    /**
     * AnnotationsScanConfig constructor.
     */
    public function __construct()
    {
        parent::__construct(self::KEY);
    }

    /**
     * Get include paths
     *
     * @return string[]
     */
    public function getIncludePaths()
    {
        return $this->includePaths;
    }

    /**
     * Set include paths
     *
     * @param string[] $includePath
     */
    public function setIncludePaths($includePath)
    {
        $this->includePaths = $includePath;
    }

    /**
     * Add include path
     *
     * @param string $includePath
     */
    public function addIncludePath(string $includePath)
    {
        $includePath = realpath($includePath);
        if ($includePath === false) {
            return;
        }
        $key = str_replace(realpath(ROOT_DIR),"",$includePath);
        $key = str_replace("/",".",$key);
        $this->includePaths[$key] = $includePath;
    }

    /**
     * Is file cache
     *
     * @return bool
     */
    public function isFileCache(): bool
    {
        return $this->fileCache;
    }

    /**
     * Set file cache
     *
     * @param bool $fileCache
     */
    public function setFileCache(bool $fileCache): void
    {
        $this->fileCache = $fileCache;
    }
}