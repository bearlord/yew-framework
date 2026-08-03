<?php
/**
 * Yew framework - RemoteEnvelope (cross-node wire format) unit tests (pure PHP)
 */

namespace Yew\Plugins\Actor\test\Actor;

use PHPUnit\Framework\TestCase;
use Yew\Plugins\Actor\Cluster\RemoteEnvelope;

class RemoteEnvelopeUnitTest extends TestCase
{
    public function testTellRoundTrip(): void
    {
        $env = new RemoteEnvelope(
            'am-1', RemoteEnvelope::KIND_TELL,
            'user-1', 'add', ['a' => 1, 'b' => 2], 'trace-xyz', 'node-a'
        );
        $json = $env->toJson();
        $back = RemoteEnvelope::fromJson($json);

        $this->assertSame('am-1', $back->msgId);
        $this->assertSame(RemoteEnvelope::KIND_TELL, $back->kind);
        $this->assertSame('user-1', $back->actorName);
        $this->assertSame('add', $back->method);
        $this->assertSame(['a' => 1, 'b' => 2], $back->arguments);
        $this->assertSame('trace-xyz', $back->traceId);
        $this->assertSame('node-a', $back->fromNode);
    }

    public function testAskReplyRoundTrip(): void
    {
        $req = new RemoteEnvelope(
            'am-9', RemoteEnvelope::KIND_ASK,
            'user-9', 'get', [], 'trace-9', 'node-a'
        );
        // Simulate the server wrapping the result into a reply envelope.
        $reply = new RemoteEnvelope(
            'am-9', RemoteEnvelope::KIND_ASK,
            'user-9', 'get', ['__reply' => ['id' => 9, 'name' => 'neo']],
            'trace-9', 'node-b'
        );

        $back = RemoteEnvelope::fromJson($reply->toJson());
        $this->assertSame('am-9', $back->msgId); // msgId links request<->reply
        $this->assertSame(['id' => 9, 'name' => 'neo'], $back->arguments['__reply']);
    }

    public function testInvalidJsonThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RemoteEnvelope::fromJson('not-json');
    }

    public function testUnicodeArgumentsPreserved(): void
    {
        $env = new RemoteEnvelope('m', RemoteEnvelope::KIND_TELL, 'a', 'say', ['msg' => '你好，世界'], null, null);
        $back = RemoteEnvelope::fromJson($env->toJson());
        $this->assertSame('你好，世界', $back->arguments['msg']);
    }
}
