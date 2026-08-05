<?php

namespace Yew\Plugins\Actor\test;

use PHPUnit\Framework\TestCase;
use Yew\Cluster\State\NodeKey;

/**
 * Offline tests for NodeKey (RSA signature / verification) without Swoole.
 */
class NodeKeyTest extends TestCase
{
    public function testSignAndVerifyRoundTrip(): void
    {
        $key = $this->generateOrSkip();
        $body = 'gossip-payload-' . random_bytes(16);

        $sig = $key->sign($body);

        $this->assertNotEmpty($sig);
        $this->assertTrue(NodeKey::verifyWith($key->getPublicKeyPem(), $body, $sig));
    }

    public function testTamperedBodyFailsVerification(): void
    {
        $key = $this->generateOrSkip();
        $sig = $key->sign('original-body');

        $this->assertFalse(NodeKey::verifyWith($key->getPublicKeyPem(), 'tampered-body', $sig));
    }

    public function testKeyIdIsStableFingerprint(): void
    {
        $key = $this->generateOrSkip();
        $this->assertSame(NodeKey::fingerprint($key->getPublicKeyPem()), $key->getKeyId());
        $this->assertSame(16, strlen($key->getKeyId()));
    }

    /**
     * openssl_pkey_new needs a usable OpenSSL config; some local CLI setups
     * (e.g. Windows without openssl.cnf) cannot generate keys. In that case
     * skip rather than fail, since the CI Linux runner has a working config.
     */
    private function generateOrSkip(): NodeKey
    {
        try {
            return NodeKey::generate();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('OpenSSL key generation unavailable: ' . $e->getMessage());
        }
    }
}
