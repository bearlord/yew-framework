<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Rpc\IdGenerator;

use Yew\Utils\Base62;
use Exception;

/**
 * Class NodeRequestIdGenerator
 */
class NodeRequestIdGenerator implements IdGeneratorInterface
{
    /**
     * @var string
     */
    private $node;

    public function generate(): string
    {
        return $this->getNode() . Base62::encode(intval(microtime(true) * 1000));
    }

    /**
     * Retrieves the node mac address and request time from ID.
     */
    public function decode(string $id): array
    {
        $len = strlen(Base62::encode(intval(microtime(true) * 1000)));
        $macStr = substr($id, 0, -$len);
        $microtime = Base62::decode(substr($id, -$len)) / 1000;
        $node = str_pad(sprintf('%x', Base62::decode($macStr)), 12, '0', STR_PAD_LEFT);
        return [
            'node' => trim(preg_replace('/(..)/', '\1:', $node), ':'),
            'time' => \DateTime::createFromFormat('U.u', (string) $microtime),
        ];
    }

    /**
     * Returns the system node ID.
     */
    public function getNode(): string
    {
        if (! $this->node) {
            $str = $this->getMacAddress() ?: $this->getIfconfig() ?: $this->randomBytes();
            $this->node = Base62::encode(hexdec($str));
        }
        return $this->node;
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function randomBytes(): string
    {
        $node = hexdec(bin2hex(random_bytes(6)));

        /**
         * Set the multicast bit.
         * @see https://tools.ietf.org/html/rfc4122#section-4.5
         */
        $node = $node | 0x010000000000;

        return str_pad(dechex($node), 12, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the network interface configuration for the system.
     *
     * @codeCoverageIgnore
     */
    protected function getIfconfig(): string
    {
        if (strpos(strtolower(ini_get('disable_functions')), 'passthru') !== false) {
            return '';
        }

        ob_start();
        switch (strtoupper(substr(php_uname('a'), 0, 3))) {
            case 'WIN':
                passthru('ipconfig /all 2>&1');
                break;
            case 'DAR':
                passthru('ifconfig 2>&1');
                break;
            case 'FRE':
                passthru('netstat -i -f link 2>&1');
                break;
            case 'LIN':
            default:
                passthru('netstat -ie 2>&1');
                break;
        }

        $output = ob_get_clean();
        $pattern = '/[^:]([0-9A-Fa-f]{2}([:-])[0-9A-Fa-f]{2}(\2[0-9A-Fa-f]{2}){4})[^:]/';
        if (preg_match_all($pattern, $output, $matches)) {
            return str_replace([':', '-'], '', $matches[1][0]);
        }
        return '';
    }

    /**
     * Returns mac address from the first system interface via the sysfs interface.
     */
    protected function getMacAddress(): string
    {
        if (strtoupper(php_uname('s')) !== 'LINUX') {
            return '';
        }
        foreach (glob('/sys/class/net/*/address', GLOB_NOSORT) as $addressPath) {
            $mac = trim(file_get_contents($addressPath));
            if (
                // Localhost adapter
                $mac !== '00:00:00:00:00:00'
                // Must match MAC address
                && preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i', $mac)
            ) {
                return str_replace(':', '', $mac);
            }
        }

        return '';
    }
}
