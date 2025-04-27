<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Pool;

use Yew\Core\Channel\Channel;
use Yew\Coroutine\Server\Server;
use Yew\Core\Pool\FrequencyInterface;

/**
 * Class Pool
 * @package Yew\Core\Pool
 */
abstract class Pool implements PoolInterface
{
    /**
     * @var Channel
     */
    protected Channel $pool;

    /**
     * @var Channel
     */
    protected Channel $channel;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var PoolOption
     */
    protected PoolOption $option;

    /**
     * @var int current connection
     */
    protected int $currentConnections = 0;

    /**
     * @var FrequencyInterface
     */
    protected FrequencyInterface $frequency;

    /**
     * @param Config $config
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->initOption($config->toConfigArray());

        $this->generateChannel();
    }


    /**
     * @return Channel
     */
    public function getChannel(): Channel
    {
        return $this->channel;
    }

    /**
     * @param Channel $channel
     * @return void
     */
    public function setChannel(Channel $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function generateChannel()
    {
        $channel = DIGet(Channel::class, [$this->option->getMaxConnections()]);

        $this->setChannel($channel);
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @param Config $config
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return PoolOption
     */
    public function getOption(): PoolOption
    {
        return $this->option;
    }

    /**
     * @param PoolOption $option
     * @return void
     */
    public function setOption(PoolOption $option): void
    {
        $this->option = $option;
    }

    /**
     * @param array $options
     * @return void
     */
    protected function initOption(array $options = [])
    {
        $this->option = new PoolOption(
            $options['minConnections'] ?? null,
            $options['maxConnections'] ?? null,
            $options['connectTimeout'] ?? null,
            $options['waitTimeout'] ?? null,
            $options['heartbeat'] ?? null,
            $options['maxIdleTime'] ?? null
        );
    }

    /**
     * @return ConnectionInterface|null
     * @throws \Throwable
     */
    public function get(): ?ConnectionInterface
    {
        $connection = $this->getConnection();

        try {
            if ($this->frequency instanceof FrequencyInterface) {
                $this->frequency->hit();

                if ($this->frequency->isLowFrequency()) {
                    $this->flush();
                }
            }
        } catch (\Exception $exception) {
            Server::$instance->getLog()->error((string) $exception);
        }

        return $connection;
    }

    /**
     * @param ConnectionInterface $connection
     * @return void
     */
    public function release(ConnectionInterface $connection): void
    {
        $this->channel->push($connection);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function flush(): void
    {
        $num = $this->getConnectionsInChannel();

        if ($num > 0) {
            while ($this->currentConnections > $this->option->getMinConnections() && $conn = $this->channel->pop(0.001)) {
                try {
                    $conn->close();
                } catch (\Exception $exception) {
                    Server::$instance->getLog()->error((string)$exception);
                } finally {
                    --$this->currentConnections;
                    --$num;
                }

                if ($num <= 0) {
                    // Ignore connections queued during flushing.
                    break;
                }
            }
        }
    }

    /**
     * @param bool $must
     * @return void
     * @throws \Exception
     */
    public function flushOne(bool $must = false): void
    {
        $num = $this->getConnectionsInChannel();
        if ($num > 0 && $conn = $this->channel->pop(0.001)) {
            if ($must || ! $conn->check()) {
                try {
                    $conn->close();
                } catch (\Exception $exception) {
                    Server::$instance->getLog()->error((string)$exception);
                } finally {
                    --$this->currentConnections;
                }
            } else {
                $this->release($conn);
            }
        }
    }

    /**
     * @return int
     */
    public function getConnectionsInChannel(): int
    {
        return $this->channel->length();
    }

    abstract protected function createConnection(): ConnectionInterface;

    /**
     * @return ConnectionInterface
     * @throws \Throwable
     */
    protected function getConnection(): ConnectionInterface
    {
        $num = $this->getConnectionsInChannel();

        try {
            if ($num === 0 && $this->currentConnections < $this->option->getMaxConnections()) {
                ++$this->currentConnections;
                return $this->createConnection();
            }
        } catch (\Throwable $throwable) {
            --$this->currentConnections;
            Server::$instance->getLog()->error($throwable->getMessage());
            throw $throwable;
        }

        $connection = $this->channel->pop($this->option->getWaitTimeout());

        if (! $connection instanceof ConnectionInterface) {
            $message = 'Connection pool exhausted. Cannot establish new connection before waitTimeOut.';

            Server::$instance->getLog()->error($message);
            throw new \RuntimeException($message);
        }
        return $connection;
    }

}
