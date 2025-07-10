<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Config;

use Yew\Core\Plugins\Event\EventDispatcher;
use Yew\Core\Server\Server;
use Symfony\Component\Yaml\Yaml;

class ConfigContext
{
    /**
     * @var array
     */
    protected array $contain = [];

    /**
     * @var array 
     */
    protected array $cacheContain = [];

    /**
     * Add a layer of configuration, sort in reverse order of depth
     *
     * @param array $config
     * @param int $deep
     */
    public function addDeepConfig(array $config, int $deep)
    {
        $this->contain[$deep] = $config;
        krsort($this->contain);

        $this->cache();
        $this->conductConfig($this->contain[$deep]);
        $eventDispatcher = Server::$instance->getContext()->getDeepByClassName(EventDispatcher::class);

        //Try to signal update
        if ($eventDispatcher instanceof EventDispatcher) {
            if (Server::$instance->getProcessManager() != null && Server::$isStart) {
                $eventDispatcher->dispatchProcessEvent(new ConfigChangeEvent(), ...Server::$instance->getProcessManager()->getProcesses());
            } else {
                $eventDispatcher->dispatchEvent(new ConfigChangeEvent());
            }
        }
    }

    /**
     * Append the same layer configuration, sort in reverse order of depth
     *
     * @param array $config
     * @param int $deep
     */
    public function appendDeepConfig(array $config, int $deep)
    {
        $oldConfig = $this->contain[$deep] ?? null;
        if ($oldConfig != null) {
            $oldConfig = array_replace_recursive($oldConfig, $config);
        } else {
            $oldConfig = $config;
        }
        $this->addDeepConfig($oldConfig, $deep);
    }

    /**
     * Multi-level sequential merge cache
     */
    protected function cache()
    {
        $this->cacheContain = array_replace_recursive(...$this->contain);
    }

    /**
     * Conduct config
     *
     * @param array $config
     */
    protected function conductConfig(array &$config)
    {
        foreach ($config as &$value) {
            if (is_array($value)) {
                $this->conductConfig($value);
            }
            if (is_string($value)) {
                //Handling the information contained in ${}
                $result = [];
                preg_match_all("/\\$\{([^\\$]*)\}/i", $value, $result);
                foreach ($result[1] as &$needConduct) {
                    $defaultArray = explode(":", $needConduct);

                    //Get constant
                    if (defined($defaultArray[0])) {
                        $evn = constant($defaultArray[0]);
                    } else {
                        //Get environment variables
                        $evn = getenv($defaultArray[0]);
                    }

                    //Get the value in config
                    if ($evn === false) {
                        $evn = $this->get($defaultArray[0]);
                    }

                    //Get the default value
                    if (empty($evn)) {
                        $evn = $defaultArray[1] ?? null;
                    }
                    $needConduct = $evn;
                }
                foreach ($result[0] as $key => $needReplace) {
                    $value = str_replace($needReplace, $result[1][$key], $value);
                }
                $this->cache();
            }
        }
    }

    /**
     * Get the value of a.b.v, the default separator is "."
     *
     * @param string $key
     * @param null $default
     * @param string|null $separator
     * @return array|mixed|null
     */
    public function get(string $key, $default = null, ?string $separator = ".")
    {
        $arr = explode($separator, $key);
        $result = $this->cacheContain;
        foreach ($arr as $value) {
            $result = $result[$value] ?? null;
            if ($result == null) {
                return $default;
            }
        }
        return $result;
    }

    /**
     * @param int $deep
     * @return array|null
     */
    public function getContainByDeep(int $deep): ?array
    {
        return $this->contain[$deep] ?? null;
    }

    /**
     * @return array
     */
    public function getCacheContain(): array
    {
        return $this->cacheContain;
    }

    public function getCacheContainYaml(): string
    {
        return Yaml::dump($this->cacheContain, 255);
    }
}
