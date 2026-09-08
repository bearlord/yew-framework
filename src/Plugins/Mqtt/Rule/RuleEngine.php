<?php

namespace Yew\Plugins\Mqtt\Rule;

use Yew\Client\HttpClient;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Mqtt\Models\MqttRule;
use Yew\Plugins\Topic\GetTopic;

/**
 * Config-driven MQTT rule engine.
 *
 * Rules live in the mqtt_rule table (loaded once into memory, reloadable at
 * runtime). For every matching MQTT event the engine evaluates the rule's
 * `filter` and, on a hit, runs its `actions`. Failures are contained so a bad
 * rule never breaks message dispatch.
 *
 * The filter evaluator here is an MVP (topic matches / payload.<field> <op> val).
 * It can later be swapped for symfony/expression-language without touching the
 * surrounding flow.
 */
class RuleEngine
{
    use GetTopic;

    private static ?RuleEngine $instance = null;

    /**
     * Compiled, enabled rules (in priority order).
     * @var array
     */
    private array $rules = [];

    private bool $loaded = false;

    /**
     * Max(updated_at) seen at last load, used for cheap change detection.
     * @var string|null
     */
    private ?string $lastVersion = null;

    /**
     * Timestamp of the last change-detection probe (microtime).
     * @var float|null
     */
    private ?float $lastCheckAt = null;

    private function __construct()
    {
        Server::$instance->getContainer()->injectOn($this);
    }

    public static function instance(): RuleEngine
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load enabled rules from the DB into memory (compiled form).
     */
    public function load(): void
    {
        $rows = MqttRule::find()
            ->where(['enabled' => 1])
            ->orderBy('priority ASC')
            ->all();

        $compiled = [];
        foreach ($rows as $row) {
            $compiled[] = [
                'id' => $row->id,
                'source' => $row->source,
                'filter' => $row->filter,
                'actions' => $row->getActionsDecoded(),
            ];
        }

        $this->rules = $compiled;
        $this->loaded = true;
        $this->lastVersion = $this->fetchMaxVersion();
    }

    /**
     * Refresh the in-memory cache from the DB (no restart needed).
     */
    public function reload(): void
    {
        $this->load();
    }

    /**
     * Cheap change detection: periodically probe the table's max(updated_at)
     * and reload only when it changed. Lets external edits (CLI / admin UI)
     * take effect at runtime without a restart.
     */
    private function maybeReload(): void
    {
        $now = microtime(true);
        if ($this->lastCheckAt !== null && ($now - $this->lastCheckAt) < 5.0) {
            return;
        }
        $this->lastCheckAt = $now;

        $max = $this->fetchMaxVersion();
        if ($max !== $this->lastVersion) {
            $this->lastVersion = $max;
            $this->load();
        }
    }

    /**
     * @return string|null
     */
    private function fetchMaxVersion(): ?string
    {
        try {
            $row = MqttRule::find()->select('MAX(updated_at) as m')->asArray()->one();
            return $row['m'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Evaluate rules for a message-publish event.
     *
     * @param array $ctx Message context: source, topic, message, client_id, qos, ...
     */
    public function onPublish(array $ctx): void
    {
        if (!$this->loaded) {
            $this->load();
        } else {
            $this->maybeReload();
        }

        $event = $ctx['source'] ?? '$events/message_publish';

        foreach ($this->rules as $rule) {
            if (($rule['source'] ?? '$events/message_publish') !== $event) {
                continue;
            }
            if (!$this->matchFilter($rule['filter'], $ctx)) {
                continue;
            }
            $this->runActions($rule['actions'], $ctx);
        }
    }

    /**
     * MVP filter evaluator. Empty filter = match all.
     */
    private function matchFilter(?string $filter, array $ctx): bool
    {
        if ($filter === null || $filter === '') {
            return true;
        }

        if (preg_match("/^topic\s+matches\s+'([^']+)'$/", $filter, $m)) {
            return $this->topicMatches($m[1], $ctx['topic'] ?? '');
        }

        if (preg_match("/^payload\.(\w+)\s*(>=|<=|!=|>|<|=)\s*(.+)$/", $filter, $m)) {
            $field = $m[1];
            $op = $m[2];
            $val = trim($m[3], "'\"");

            $payload = json_decode($ctx['message'] ?? '', true);
            if (!is_array($payload) || !array_key_exists($field, $payload)) {
                return false;
            }

            return $this->compare($payload[$field], $op, $val);
        }

        // Unknown filter syntax -> safe no-match.
        return false;
    }

    private function topicMatches(string $pattern, string $topic): bool
    {
        $regex = '/^' . str_replace(
            ['\+', '#'],
            ['[^/]+', '.*'],
            preg_quote($pattern, '/')
        ) . '$/';

        return (bool) preg_match($regex, $topic);
    }

    private function compare($left, string $op, $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            $left = (float) $left;
            $right = (float) $right;
        }

        switch ($op) {
            case '>=':
                return $left >= $right;
            case '<=':
                return $left <= $right;
            case '>':
                return $left > $right;
            case '<':
                return $left < $right;
            case '!=':
                return $left != $right;
            case '=':
                return $left == $right;
            default:
                return false;
        }
    }

    private function runActions(array $actions, array $ctx): void
    {
        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $args = $action['args'] ?? [];

            switch ($type) {
                case 'republish':
                    $this->actionRepublish($args, $ctx);
                    break;
                case 'log':
                    $this->actionLog($args, $ctx);
                    break;
                case 'http':
                    $this->actionHttp($args, $ctx);
                    break;
                default:
                    break;
            }
        }
    }

    private function actionRepublish(array $args, array $ctx): void
    {
        $topic = $args['topic'] ?? ($ctx['topic'] ?? '');
        $payload = $this->renderTemplate($args['payload'] ?? null, $ctx) ?? $ctx['message'];

        $this->publish($topic, $payload);
    }

    private function actionLog(array $args, array $ctx): void
    {
        $msg = $this->renderTemplate($args['message'] ?? null, $ctx)
            ?? json_encode($ctx, JSON_UNESCAPED_SLASHES);

        Server::$instance->getLog()->info('[RuleEngine] ' . $msg);
    }

    /**
     * HTTP action: POST/GET/etc. the rendered payload to a configured URL.
     *
     * Args:
     *  - url:    target URL (supports ${field} placeholders)
     *  - method: HTTP method, defaults to POST
     *  - headers: associative array of extra request headers
     *  - body:   request body template (supports ${field} placeholders)
     *
     * Failures are logged and swallowed so a dead endpoint never breaks dispatch.
     */
    private function actionHttp(array $args, array $ctx): void
    {
        $rawUrl = $this->renderTemplate($args['url'] ?? '', $ctx);
        if ($rawUrl === '') {
            return;
        }

        $method = strtoupper((string) ($args['method'] ?? 'POST'));
        $headers = is_array($args['headers'] ?? null) ? $args['headers'] : [];
        $body = isset($args['body']) ? $this->renderTemplate($args['body'], $ctx) : null;

        $parts = parse_url($rawUrl);
        if ($parts === false || empty($parts['host'])) {
            Server::$instance->getLog()->warning('[RuleEngine] http action invalid url: ' . $rawUrl);
            return;
        }

        $ssl = ($parts['scheme'] ?? '') === 'https';
        $port = (int) ($parts['port'] ?? ($ssl ? 443 : 80));
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        try {
            $client = new HttpClient($parts['host'], $port, $ssl, ['headers' => $headers]);

            if ($method === 'GET') {
                $client->get($path, $headers);
            } elseif ($method === 'POST') {
                $client->post($path, $body ?? '', $headers);
            } else {
                if ($body !== null) {
                    $client->getClient()->setData($body);
                }
                $client->execute($path, $method);
            }

            $status = $client->getStatusCode();
            if ($status < 200 || $status >= 300) {
                Server::$instance->getLog()->warning(
                    sprintf('[RuleEngine] http action %s %s returned %d', $method, $rawUrl, $status)
                );
            } else {
                Server::$instance->getLog()->info(
                    sprintf('[RuleEngine] http action %s %s -> %d', $method, $rawUrl, $status)
                );
            }
            $client->close();
        } catch (\Throwable $e) {
            Server::$instance->getLog()->warning('[RuleEngine] http action failed: ' . $e->getMessage());
        }
    }

    /**
     * Replace ${field} placeholders with context values.
     *
     * @param mixed $tpl
     * @param array $ctx
     * @return mixed
     */
    private function renderTemplate($tpl, array $ctx)
    {
        if (!is_string($tpl)) {
            return $tpl;
        }

        return preg_replace_callback('/\$\{(\w+)\}/', function ($m) use ($ctx) {
            return $ctx[$m[1]] ?? '';
        }, $tpl);
    }
}
