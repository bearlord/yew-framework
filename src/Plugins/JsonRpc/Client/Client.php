<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc\Client;

USE Yew\Coroutine\Server\Server;
use Yew\Plugins\JsonRpc\Packer\JsonEofPacker;
use Yew\Plugins\JsonRpc\Packer\JsonLengthPacker;
use Yew\Plugins\JsonRpc\Packer\JsonPacker;
use Yew\Plugins\JsonRpc\Packer\PackerInterface;
use Yew\Plugins\JsonRpc\Protocol;
use Yew\Plugins\JsonRpc\Transporter\JsonRpcHttpTransporter;
use Yew\Plugins\JsonRpc\Transporter\JsonRpcPoolTransporter;
use Yew\Plugins\JsonRpc\Transporter\JsonRpcStreamTransport;
use Yew\Plugins\JsonRpc\Transporter\JsonRpcTransporter;
use Yew\Plugins\JsonRpc\Transporter\TransporterInterface;
use Yew\Rpc\Client\AbstractServiceClient;

class Client extends \Yew\Rpc\Client\Client
{
    /**
     * @var string
     */
    public string $protocol = '';

    /**
     * @var array
     */
    public array $config = [];

    /**
     * @var null|PackerInterface
     */
    protected $packer;

    /**
     * @var null|TransporterInterface
     */
    protected $transporter;

    /**
     * @var null|TransporterInterface
     */
    protected $transporterInstance;

    public function __construct(array $config = [], ?string $protocol = null)
    {
        $this->config = $config;
        $this->setProtocol($protocol);
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
            $this->protocol = Protocol::PROTOCOL_JSON_RPC_HTTP;
        }

        return $this->protocol;
    }

    /**
     * @return Protocol
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getProtocolInstance(): Protocol
    {
        return Yew::createObject(Protocol::class);
    }

    /**
     * @return string
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getPacker()
    {
        return $this->getProtocolInstance()->getPacker($this->protocol);
    }

    /**
     * @return PackerInterface|JsonEofPacker|JsonLengthPacker|JsonPacker
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getPackerInstance()
    {
        $packer = $this->getPacker();

        $params = [
            'class' => $packer
        ];
        if (!empty($this->config['setting'])) {
            $params = array_merge($params, $this->config['setting']);
        }

        return Yew::createObject($params);
    }

    /**
     * @return string
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getTransporter()
    {
        return $this->getProtocolInstance()->getTransporter($this->protocol);
    }

    /**
     * @return TransporterInterface|JsonRpcHttpTransporter|JsonRpcTransporter|JsonRpcPoolTransporter
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function getTransporterInstance()
    {
        if (empty($this->transporterInstance)) {
            $this->transporterInstance = Yew::createObject($this->getTransporter(), [
                $this->config
            ]);
        }

        return $this->transporterInstance;
    }

    /**
     * @param $data
     * @return mixed|null
     * @throws \Yew\Framework\Base\InvalidConfigException
     */
    public function send($data)
    {
        $packer = $this->getPackerInstance();
        $packedData = $packer->pack($data);

        $response = $this->getTransporterInstance()->send($packedData);

        return $packer->unpack((string)$response);
    }

}