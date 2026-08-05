<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Cluster\Transport;

/**
 * Real cross-node transport over TCP (Akka remoting / Orleans silo-to-silo
 * equivalent for this framework).
 *
 * Wire format: newline-delimited JSON {@see RemoteEnvelope}. The same class
 * acts as both server (binds clusterPort, hands inbound envelopes to the local
 * actor via the in-process IPC proxy) and client (opens a short-lived coroutine
 * connection to deliver tell/ask to a remote node).
 *
 * ask() is implemented with a request/reply envelope over one connection, so it
 * is a genuine request-response across the network â€?not emulated locally.
 */
class TcpRemoteTransport implements RemoteTransport
{
    private string $host;
    private int $port;
    private string $localNodeId;
    private ?\Swoole\Coroutine\Server $server = null;
    /** @var callable(RemoteEnvelope):mixed|null Injected request handler (actor layer). */
    private $inboundHandler = null;

    public function __construct(string $host, int $port, string $localNodeId)
    {
        $this->host = $host;
        $this->port = $port;
        $this->localNodeId = $localNodeId;
    }

    /**
     * Inject the handler for inbound (cross-node) requests. The callback
     * receives the decoded {@see RemoteEnvelope} and returns the actor's result
     * (for ask) or null (for tell / not-found). Owned by the actor layer so this
     * transport stays free of any actor-package dependency.
     *
     * @param callable(RemoteEnvelope):mixed $handler
     */
    public function setInboundHandler(callable $handler): void
    {
        $this->inboundHandler = $handler;
    }

    /**
     * Start the inbound server. Each accepted connection is handled in its own
     * coroutine; envelopes are read line-by-line (newline-delimited JSON).
     */
    public function start(): void
    {
        if ($this->port <= 0) {
            return;
        }
        $this->server = new \Swoole\Coroutine\Server($this->host, $this->port, false, true);
        $this->server->handle(function (\Swoole\Coroutine\Server\Connection $conn) {
            while (true) {
                $line = $conn->recv(60);
                if ($line === '' || $line === false) {
                    break;
                }
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                try {
                    $envelope = RemoteEnvelope::fromJson($line);
                } catch (\Throwable $e) {
                    continue;
                }
                $this->handleInbound($conn, $envelope);
            }
        });
        goWithContext(function () {
            $this->server->start();
        });
    }

    /**
     * Deliver an inbound envelope to the locally-resident actor and, for ask,
     * write the reply back over the same connection. The actual actor delivery
     * is delegated to the injected handler (actor layer); this method only
     * owns the network framing.
     */
    private function handleInbound(\Swoole\Coroutine\Server\Connection $conn, RemoteEnvelope $env): void
    {
        if ($this->inboundHandler === null) {
            // No actor layer attached: answer asks with an empty result so the
            // remote caller does not hang.
            if ($env->kind === RemoteEnvelope::KIND_ASK) {
                $conn->send($this->replyEnvelope($env, null)->toJson() . "\n");
            }
            return;
        }

        $result = ($this->inboundHandler)($env);

        if ($env->kind === RemoteEnvelope::KIND_ASK) {
            $conn->send($this->replyEnvelope($env, $result)->toJson() . "\n");
        }
    }

    /**
     * Build the reply envelope for a request (result under arguments['__reply']).
     *
     * @param RemoteEnvelope $req The original request
     * @param mixed $result The actor's return value
     * @return RemoteEnvelope
     */
    private function replyEnvelope(RemoteEnvelope $req, $result): RemoteEnvelope
    {
        return new RemoteEnvelope(
            $req->msgId,
            RemoteEnvelope::KIND_ASK, // reuse kind; client distinguishes by msgId
            $req->actorName,
            $req->method,
            ['__reply' => $result],
            $req->traceId,
            $this->localNodeId
        );
    }

    /**
     * Fire-and-forget delivery to a remote actor.
     *
     * @param Location $location Target location (remote node)
     * @param string $method Actor method to invoke
     * @param array $arguments Method arguments
     * @param string|null $traceId Trace id for cross-node propagation
     * @return bool True if the envelope was dispatched
     */
    public function tell(Location $location, string $method, array $arguments, ?string $traceId): bool
    {
        $env = new RemoteEnvelope(
            $this->newMsgId(), RemoteEnvelope::KIND_TELL,
            $this->actorNameFrom($location), $method, $arguments, $traceId, $this->localNodeId
        );
        return $this->sendOnce($location, $env) !== null;
    }

    /**
     * Request-response delivery to a remote actor; blocks for the reply.
     *
     * @param Location $location Target location (remote node)
     * @param string $method Actor method to invoke
     * @param array $arguments Method arguments
     * @param string|null $traceId Trace id for cross-node propagation
     * @param float $timeOut Seconds to wait for the reply
     * @return mixed The remote return value, or null on timeout/error
     */
    public function ask(Location $location, string $method, array $arguments, ?string $traceId, float $timeOut)
    {
        $env = new RemoteEnvelope(
            $this->newMsgId(), RemoteEnvelope::KIND_ASK,
            $this->actorNameFrom($location), $method, $arguments, $traceId, $this->localNodeId
        );
        $reply = $this->sendOnce($location, $env, max(1.0, $timeOut + 5));
        if ($reply === null) {
            return null;
        }
        return ($reply->arguments['__reply'] ?? null);
    }

    /**
     * Open a short-lived coroutine TCP connection, send the envelope, and (for
     * ask) read the reply envelope back. Returns the reply envelope or null.
     */
    private function sendOnce(Location $location, RemoteEnvelope $env, float $readTimeout = 0): ?RemoteEnvelope
    {
        $node = $location->getNode();
        $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
        if (!$client->connect($node->getHost(), $node->getPort(), 5.0)) {
            return null;
        }
        $client->send($env->toJson() . "\n");

        $reply = null;
        if ($env->kind === RemoteEnvelope::KIND_ASK) {
            $line = $client->recv($readTimeout);
            if (is_string($line) && trim($line) !== '') {
                try {
                    $r = RemoteEnvelope::fromJson(trim($line));
                    if ($r->msgId === $env->msgId) {
                        $reply = $r;
                    }
                } catch (\Throwable $e) {
                    $reply = null;
                }
            }
        }
        $client->close();
        return $reply;
    }

    /**
     * Whether this transport can reach the given (remote) location.
     *
     * @param Location $location Target location
     * @return bool
     */
    public function supports(Location $location): bool
    {
        $node = $location->getNode();
        return $node !== null && !$node->isLocal();
    }

    /**
     * Read the actor name carried on the location.
     *
     * @param Location $location
     * @return string
     */
    private function actorNameFrom(Location $location): string
    {
        // The remote proxy stores the actor name on the Location via setActorName.
        return $location->getActorName() ?? '';
    }

    /**
     * Generate a unique message id for request/reply correlation.
     *
     * @return string
     */
    private function newMsgId(): string
    {
        return uniqid('am-', true);
    }
}
