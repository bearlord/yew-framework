<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc\Client;

use ESD\Core\Exception;
use ESD\Core\Server\Server;
use ESD\LoadBalance\Node;
use ESD\Plugins\JsonRpc\Protocol;
use Yew\Rpc\RpcException;
use ESD\Yii\Base\Component;
use ESD\Yii\Helpers\ArrayHelper;
use ESD\Yii\Yii;

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

    /**
     * @var array
     */
    public array $nodes = [];

    /**
     * @var Node
     */
    public $node;

    /**
     * @var string
     */
    public string $host = '';

    /**
     * @var string
     */
    public string $port = '';

    /**
     * @var Client
     */
    public $client;

    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @param string $serviceName
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }


    /**
     * @param string $protocol
     */
    public function setProtocol(string $protocol): void
    {
        $this->protocol = $protocol;
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        if (empty($this->protocol)) {
            $config = $this->getConfig();
            $this->protocol = !empty($config['protocol']) ? $config['protocol'] : Protocol::PROTOCOL_JSON_RPC_HTTP;
        }

        return $this->protocol;
    }

    /**
     * @return array
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @param array $nodes
     */
    public function setNodes(array $nodes): void
    {
        $this->nodes = $nodes;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getPort(): string
    {
        return $this->port;
    }

    /**
     * @param string $port
     */
    public function setPort(string $port): void
    {
        $this->port = $port;
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }
}
