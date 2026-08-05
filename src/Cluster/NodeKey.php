<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster;

/**
 * Per-node asymmetric key (node "certificate").
 *
 * Replaces the shared symmetric secret used previously. Each node holds its own
 * private key and publishes its public key to peers (carried inside the gossip
 * SYNC/SYN-ACK `self` member record). Peers verify inbound messages with the
 * sender's public key, so a single leaked node private key only compromises
 * that node â€?not the whole cluster.
 *
 *  - sign():  produce a detached signature over a canonical body with the
 *             node's private key (SHA256 + RSA or EC, whatever openssl loaded).
 *  - verify(): check a signature against a *public* key (the sender's).
 *  - keyId:   a short, stable fingerprint of the public key (SHA256 hex of the
 *             PEM, truncated). Carried on every message so the receiver can pick
 *             the right public key when keys are rotated (forward secrecy).
 *
 * Trust model: by default any public key learned from a peer's SYNC is accepted
 * (authenticated discovery). Optionally a static trust store (nodeId => pubkey)
 * can be supplied to pin identities and reject unknown/forged nodes.
 */
class NodeKey
{
    private string $privateKeyPem;
    private string $publicKeyPem;
    private string $keyId;

    /**
     * Generate a fresh key pair (default: RSA 2048). For production you may pass
     * a stronger config, e.g. ['private_key_type' => OPENSSL_KEYTYPE_EC, ...].
     */
    public static function generate(array $opensslConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]): self
    {
        $res = openssl_pkey_new($opensslConfig);
        if ($res === false) {
            throw new \RuntimeException('openssl_pkey_new failed: ' . openssl_error_string());
        }
        $priv = '';
        openssl_pkey_export($res, $priv);
        $pub = openssl_pkey_get_details($res)['key'];
        return new self($priv, $pub);
    }

    /**
     * Load from PEM strings (e.g. read from config / mounted secret).
     */
    public static function fromPem(string $privateKeyPem, string $publicKeyPem): self
    {
        return new self($privateKeyPem, $publicKeyPem);
    }

    /**
     * Build a key from PEM strings (used by generate()/fromPem()).
     *
     * @param string $privateKeyPem PEM private key
     * @param string $publicKeyPem PEM public key
     */
    public function __construct(string $privateKeyPem, string $publicKeyPem)
    {
        $this->privateKeyPem = $privateKeyPem;
        $this->publicKeyPem = $publicKeyPem;
        $this->keyId = self::fingerprint($publicKeyPem);
    }

    /**
     * Detached signature (base64) over an arbitrary string body.
     */
    public function sign(string $body): string
    {
        $ok = openssl_sign($body, $sig, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
        if ($ok === false) {
            throw new \RuntimeException('openssl_sign failed: ' . openssl_error_string());
        }
        return base64_encode($sig);
    }

    /**
     * Verify a detached base64 signature against a public-key PEM.
     */
    public static function verifyWith(string $publicKeyPem, string $body, string $sigB64): bool
    {
        $sig = base64_decode($sigB64, true);
        if ($sig === false) {
            return false;
        }
        $res = openssl_pkey_get_public($publicKeyPem);
        if ($res === false) {
            return false;
        }
        $r = openssl_verify($body, $sig, $res, OPENSSL_ALGO_SHA256);
        return $r === 1;
    }

    /**
     * PEM-encoded public key.
     *
     * @return string
     */
    public function getPublicKeyPem(): string
    {
        return $this->publicKeyPem;
    }

    /**
     * PEM-encoded private key.
     *
     * @return string
     */
    public function getPrivateKeyPem(): string
    {
        return $this->privateKeyPem;
    }

    /**
     * Short stable fingerprint of the public key.
     *
     * @return string
     */
    public function getKeyId(): string
    {
        return $this->keyId;
    }

    /**
     * Stable fingerprint of a public key PEM (used as keyId and for trust pinning).
     */
    public static function fingerprint(string $publicKeyPem): string
    {
        return substr(hash('sha256', $publicKeyPem), 0, 16);
    }
}
