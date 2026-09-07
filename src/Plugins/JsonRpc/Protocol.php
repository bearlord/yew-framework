<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc;

use Yew\Plugins\JsonRpc\Packer\JsonEofPacker;
use Yew\Plugins\JsonRpc\Packer\JsonLengthPacker;
use Yew\Plugins\JsonRpc\Packer\JsonPacker;
use Yew\Plugins\JsonRpc\Transporter\JsonRpcHttpTransporter;
use Yew\Plugins\JsonRpc\Transporter\JsonRpcTransporter;

class Protocol
{
    const PROTOCOL_JSON_RPC = "jsonrpc";

    const PROTOCOL_JSON_RPC_TCP_LENGTH_CHECK = "jsonrpc-tcp-length-check";

    const PROTOCOL_JSON_RPC_HTTP = "jsonrpc-http";

    /**
     * @var array
     */
    protected $protocols = [
        self::PROTOCOL_JSON_RPC => [
            "packer" => JsonEofPacker::class,
            "transporter" => JsonRpcTransporter::class
        ],

        self::PROTOCOL_JSON_RPC_TCP_LENGTH_CHECK => [
            "packer" => JsonLengthPacker::class,
            "transporter" => JsonRpcTransporter::class
        ],

        self::PROTOCOL_JSON_RPC_HTTP => [
            "packer" => JsonPacker::class,
            "transporter" => JsonRpcHttpTransporter::class
        ]
    ];

    /**
     * @param string $protocol
     * @return string
     */
    public function getPacker(string $protocol): string
    {
        if (empty($this->protocols[$protocol])) {
            throw new \Yew\Framework\Base\InvalidArgumentException(sprintf('Unknown JsonRpc protocol [%s] for packer.', $protocol));
        }

        return $this->protocols[$protocol]["packer"];
    }

    /**
     * @param string $protocol
     * @return string
     */
    public function getTransporter(string $protocol): string
    {
        if (empty($this->protocols[$protocol])) {
            throw new \Yew\Framework\Base\InvalidArgumentException(sprintf('Unknown JsonRpc protocol [%s] for transporter.', $protocol));
        }

        return $this->protocols[$protocol]["transporter"];
    }
}