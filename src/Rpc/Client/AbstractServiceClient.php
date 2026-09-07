<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc\Client;

use Yew\Core\Exception;
use Yew\Core\Server\Server;
use Yew\LoadBalance\Node;
use Yew\Plugins\JsonRpc\Protocol;
use Yew\Rpc\RpcException;
use Yew\Framework\Base\Component;
use Yew\Framework\Helpers\ArrayHelper;
use Yew\Yew;

abstract class AbstractServiceClient extends Component
{
    /**
     * @var string The service name of the target service.
     */
    public string $serviceName = '';

    /**
     * @var string The protocol of the target service
     */
    public string $protocol = '';

    public array $nodes = [];

    public ?Node $node = null;

    public string $host = '';

    public int $port = 0;

    public ?Client $client = null;

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }


    public function setProtocol(string $protocol): void
    {
        $this->protocol = $protocol;
    }

    public function getProtocol(): string
    {
        if (empty($this->protocol)) {
            $config = $this->getConfig();
            $this->protocol = !empty($config['protocol']) ? $config['protocol'] : Protocol::PROTOCOL_JSON_RPC_HTTP;
        }

        return $this->protocol;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function setNodes(array $nodes): void
    {
        $this->nodes = $nodes;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }
}
