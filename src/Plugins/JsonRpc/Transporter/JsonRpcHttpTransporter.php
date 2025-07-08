<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc\Transporter;

use Yew\LoadBalance\Algorithm\Random;
use Yew\LoadBalance\Algorithm\RoundRobin;
use Yew\LoadBalance\Algorithm\WeightedRandom;
use Yew\LoadBalance\Algorithm\WeightedRoundRobin;
use Yew\LoadBalance\LoadBalancerInterface;
use Yew\LoadBalance\LoadBalancerManager;
use Yew\LoadBalance\Node;
use Yew\Framework\Base\BaseObject;
use Yew\Framework\Base\Component;
use Yew\Framework\Base\InvalidArgumentException;
use Yew\Framework\Helpers\Json;
use Yew\Framework\HttpClient\Client;
use Yew\Framework\HttpClient\CurlFormatter;
use Yew\Framework\HttpClient\CurlTransport;
use Yew\Framework\Yii;
use Swlib\Saber;
use Swoole\Coroutine\Channel;

/**
 * Class JsonRpcHttpTransporter
 * @package Yew\Plugins\JsonRpc\Transporter
 */
class JsonRpcHttpTransporter extends Component implements TransporterInterface
{

    public $config = [];

    /**
     * The service name of the target service.
     *
     * @var string
     */
    protected $serviceName = '';

    /**
     * @var float
     */
    public $connectTimeout = 5;

    /**
     * @var float
     */
    public $receiveTimeout = 5;

    /**
     * If $loadBalancer is null, will select a node in $nodes to request,
     * otherwise, use the nodes in $loadBalancer.
     *
     * @var array
     */
    public $nodes = [];

    /**
     * Static node, skip loadBalance and select a node from the nodes
     * @var Node
     */
    public $node;

    /**
     * @var null|LoadBalancerInterface|Random|RoundRobin|WeightedRandom|WeightedRoundRobin
     */
    private $loadBalancer;

    /**
     * The load balancer of the client, this name of the load balancer
     * needs to register into \Yew\LoadBalancer\LoadBalancerManager.
     *
     * @var string
     */
    public $loadBalancerAlgorithm = 'random';

    /**
     * JsonRpcHttpTransporter constructor.
     * @param array $config
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->serviceName = $config['name'];
        $this->nodes = $config['nodes'];

        if (!empty($config['connectTimeout'])) {
            $this->connectTimeout = $config['connectTimeout'];
        }
        if (!empty($config['receiveTimeout'])) {
            $this->receiveTimeout = $config['receiveTimeout'];
        }
        if (!empty($config['loadBalancer'])) {
            $this->loadBalancerAlgorithm = $config['loadBalancer'];
        }
    }

    /**
     * @param array $nodes
     */
    public function setNodes(array $nodes): void
    {
        $this->nodes = $nodes;
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
     * @return \Yew\LoadBalance\LoadBalancerInterface
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function createLoadBalancer(array $nodes)
    {
        /** @var LoadBalancerManager $loadBalanceManager */
        $loadBalanceManager = Yii::createObject(LoadBalancerManager::class);
        $loadBalance = $loadBalanceManager->getInstance($this->serviceName, $this->loadBalancerAlgorithm)->setNodes($nodes);

        return $loadBalance;
    }

    /**
     * @param LoadBalancerInterface $loadBalancer
     * @return TransporterInterface
     */
    public function setLoadBalancer(LoadBalancerInterface $loadBalancer): TransporterInterface
    {
        $this->loadBalancer = $loadBalancer;
        return $this;
    }

    /**
     * @return LoadBalancerInterface|null
     */
    public function getLoadBalancer(): ?LoadBalancerInterface
    {
        return $this->loadBalancer;
    }

    /**
     * @param array $node
     */
    public function setNode(array $node)
    {
        if (!is_int($node['port'])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid node config [%s], the port option has to a integer.',
                implode(':', $node)));
        }
        $schema = $node['schema'] ?? null;
        $path = $node['path'] ?? null;
        $weight = $node['weight'] ?? 0;
        $this->node = new Node($schema, $node['host'], $node['port'], $path, $weight);
    }

    /**
     * If the load balancer is exists, then the node will select by the load balancer,
     * otherwise will get a random node.
     */
    public function getNode(): Node
    {
        if (!empty($this->node)) {
            return $this->node;
        }

        if (empty($this->loadBalancer)) {
            $loadBalancer = $this->createLoadBalancer($this->createNodes());
            $this->setLoadBalancer($loadBalancer);
        }

        if ($this->loadBalancer instanceof LoadBalancerInterface) {
            return $this->loadBalancer->select();
        }
        return $this->nodes[array_rand($this->nodes)];
    }

    /**
     * Create nodes the first time.
     *
     * @return array [array, callable]
     */
    protected function createNodes(): array
    {
        $consumer = $this->config;

        // Not exists the registry config, then looking for the 'nodes' property.
        if (isset($consumer['nodes'])) {
            $nodes = [];
            foreach ($consumer['nodes'] ?? [] as $item) {
                if (isset($item['host'], $item['port'])) {
                    if (!is_int($item['port'])) {
                        throw new InvalidArgumentException(sprintf(
                            'Invalid node config [%s], the port option has to a integer.',
                            implode(':', $item)));
                    }
                    $schema = $item['schema'] ?? null;
                    $path = $item['path'] ?? null;
                    $weigth = $item['weight'] ?? 0;
                    $nodes[] = new Node($schema, $item['host'], $item['port'], $path, $weigth);
                }
            }
            return $nodes;
        }

        throw new InvalidArgumentException('Config of registry or nodes missing.');
    }

    /**
     * @param string $data
     * @return mixed
     */
    public function send(string $data)
    {
        $node = $this->getNode();

        $url = sprintf("%s://%s:%s/%s",
            $node->getSchema(),
            $node->getHost(),
            $node->getPort(),
            $node->getPath()
        );

        enableRuntimeCoroutine(true, SWOOLE_HOOK_ALL);
        $channel = new Channel(1);
        goWithContext(function () use ($channel, $url, $node, $data) {
            $saber = Saber::create([
                'headers' => [
                    'Content-Type' => 'application/json; charset=UTF-8',
                ]
            ]);
            $responeData = $saber->post($url, $data)->getBody();
            $channel->push($responeData);

            $this->loadBalancer->removeNode($node);
        });

        $response = $channel->pop();
        return $response;
    }

    public function recv()
    {
        throw new \RuntimeException(__CLASS__ . ' does not support recv method.');
    }


}