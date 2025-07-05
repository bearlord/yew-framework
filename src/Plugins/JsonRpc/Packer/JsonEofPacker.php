<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc\Packer;

use Yew\Framework\Base\Component;

class JsonEofPacker extends Component implements PackerInterface
{
    /**
     * @var string
     */
    protected string $eof = "";

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->eof = $options['settings']['package_eof'] ?? "\r\n";
    }

    /**
     * @param $data
     * @return string
     */
    public function pack($data): string
    {
        $data = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        return $data . $this->eof;
    }

    /**
     * @param string $data
     * @return mixed
     */
    public function unpack(string $data)
    {
        $data = rtrim($data, $this->eof);
        
        return json_decode($data, true);
    }

}