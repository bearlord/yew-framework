<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc\Packer;

use Yew\Framework\Base\Component;

class JsonPacker extends Component implements PackerInterface
{
    /**
     * @param $data
     * @return string
     */
    public function pack($data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param string $data
     * @return mixed
     */
    public function unpack(string $data)
    {
        return json_decode($data, true);
    }
}