<?php


namespace Yew\Plugins\Queue;


use Yew\Core\Channel\Channel;
use Yew\Framework\Queue\Cli\Queue;
use Yew\Yew;

class QueuePool
{
    /**
     * @var string
     */
    protected $name;
    /**
     * @var Channel
     */
    protected $pool;

    /** @var array  */
    protected $config;

    /** @var int  */
    protected $poolMaxNumber = 5;

    /**
     * QueuePool constructor.
     * @param string $name
     * @param array $config
     */
    public function __construct(string $name, array $config)
    {
        $this->setName($name);
        $this->setConfig($config);

        $this->pool = DIGet(Channel::class, [$this->getPoolMaxNumber()]);
        for ($i = 0; $i < $this->getPoolMaxNumber(); $i++) {
            $queue = $this->buildQueue($config);
            $this->pool->push($queue);
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param Config $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }


    /**
     * @return int|mixed
     */
    protected function getPoolMaxNumber()
    {
        return $this->config['poolMaxNumber'] ?? $this->poolMaxNumber;
    }

    /**
     * @param $config
     * @return object
     */
    public function buildQueue($config)
    {
        if (!empty($config['minIntervalTime'])) {
            unset($config['minIntervalTime']);
        }
        if (!empty($config['poolMaxNumber'])) {
            unset($config['poolMaxNumber']);
        }

        return Yew::createObject($config);
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        $contextKey = sprintf("Queue:%s", $this->name);
        $handle = getContextValue($contextKey);

        if ($handle == null) {
            /** @var \Yew\Framework\Queue\Cli\Queue $handle */
            $handle = $this->pool->pop();

            \Swoole\Coroutine::defer(function () use ($handle) {
                $this->pool->push($handle);
            });
            setContextValue($contextKey, $handle);
        }
        return $handle;
    }

}
