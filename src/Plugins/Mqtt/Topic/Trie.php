<?php

namespace Yew\Plugins\Mqtt\Topic;

/**
 * Subscription matching index (Trie) for the MQTT broker, hosted inside the
 * mqtt-connection helper process.
 *
 * This is a local copy of Yew\Plugins\Topic\Trie so the Mqtt plugin does not
 * depend on the Topic plugin. The Trie is maintained incrementally: insert()
 * on subscribe, remove() on unsubscribe, and serves fast O(levels) matching
 * on publish.
 *
 * Node shape:
 *   'exact' => [segment => childNode]   literal segment branch
 *   'plus'  => childNode|null           single-level '+' branch
 *   'hash'      => [clientId => clientId]|null   '#' subscribers at this prefix
 *   'clientIds' => [clientId => clientId]         exact subscribers ending here
 */
class Trie
{
	/**
	 * Root node of the Trie.
	 * @var array
	 */
	private array $root;

	public function __construct()
	{
		$this->root = $this->newNode();
	}

	/**
	 * Wipe the index (kept for symmetry with the subscriptions store).
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$this->root = $this->newNode();
	}

	/**
	 * Build an empty Trie node.
	 *
	 * @return array
	 */
	private function newNode(): array
	{
		return ['exact' => [], 'plus' => null, 'hash' => null, 'clientIds' => []];
	}

	/**
	 * Insert a subscription filter (and its clientId) into the Trie.
	 *
	 * '+' descends the single-level branch, '#' terminates the current node
	 * (multi-level wildcard), any other segment descends the literal branch.
	 *
	 * @param string $filter   Subscription filter.
	 * @param string $clientId Subscriber client_id.
	 * @return void
	 */
	public function insert(string $filter, string $clientId): void
	{
		if ($clientId === '') {
			return;
		}

		$segments = explode('/', $filter);
		$node = &$this->root;
		foreach ($segments as $seg) {
			if ($seg === '#') {
				if ($node['hash'] === null) {
					$node['hash'] = [];
				}
				$node['hash'][$clientId] = $clientId;
				return;
			}
			if ($seg === '+') {
				if ($node['plus'] === null) {
					$node['plus'] = $this->newNode();
				}
				$node = &$node['plus'];
				continue;
			}
			if (!isset($node['exact'][$seg])) {
				$node['exact'][$seg] = $this->newNode();
			}
			$node = &$node['exact'][$seg];
		}
		$node['clientIds'][$clientId] = $clientId;
	}

	/**
	 * Remove a clientId from the Trie node that the filter resolves to.
	 *
	 * Empty nodes are left in place (harmless: only stored clientIds are matched).
	 *
	 * @param string $filter   Subscription filter.
	 * @param string $clientId Subscriber client_id.
	 * @return void
	 */
	public function remove(string $filter, string $clientId): void
	{
		if ($clientId === '') {
			return;
		}

		$segments = explode('/', $filter);
		$node = &$this->root;
		foreach ($segments as $seg) {
			if ($seg === '#') {
				if (isset($node['hash'][$clientId])) {
					unset($node['hash'][$clientId]);
				}
				return;
			}
			if ($seg === '+') {
				if ($node['plus'] === null) {
					return;
				}
				$node = &$node['plus'];
				continue;
			}
			if (!isset($node['exact'][$seg])) {
				return;
			}
			$node = &$node['exact'][$seg];
		}
		if (isset($node['clientIds'][$clientId])) {
			unset($node['clientIds'][$clientId]);
		}
	}

	/**
	 * Resolve all subscriber clientIds for a published topic by walking the Trie.
	 *
	 * Cost is O(number of topic levels) per call, independent of the number of
	 * wildcards. A '$'-prefixed (MQTT System) topic never matches the root
	 * '+' / '#' branches, preserving the System Topic protection.
	 *
	 * @param string $topic Published topic.
	 * @return array Map of clientId => clientId (de-duplicated).
	 */
	public function match(string $topic): array
	{
		if ($topic === '') {
			return [];
		}
		$segments = explode('/', $topic);
		$isSys = $topic[0] === '$';
		$result = [];
		$this->walk($this->root, $segments, 0, $isSys, $result);
		return $result;
	}

	/**
	 * Recursive Trie walk that collects matching clientIds.
	 *
	 * At every visited node we collect its '#' subscribers (prefix + below).
	 * Exact subscribers at a node are collected only when the walk reaches the
	 * published topic's final level (so 'a/b' does not match 'a/b/c').
	 * The root '+'/'#' branches are skipped for System topics.
	 *
	 * @param array  $node
	 * @param array  $segments
	 * @param int    $depth
	 * @param bool   $isSys
	 * @param array  $result
	 * @return void
	 */
	private function walk(array &$node, array $segments, int $depth, bool $isSys, array &$result): void
	{
		$blockSys = ($depth === 0 && $isSys);

		if (!$blockSys && $node['hash'] !== null) {
			foreach ($node['hash'] as $clientId) {
				$result[$clientId] = $clientId;
			}
		}

		$total = count($segments);
		if ($depth === $total && $node['clientIds'] !== []) {
			foreach ($node['clientIds'] as $clientId) {
				$result[$clientId] = $clientId;
			}
		}

		if ($depth === $total) {
			return;
		}

		$seg = $segments[$depth];
		if (isset($node['exact'][$seg])) {
			$this->walk($node['exact'][$seg], $segments, $depth + 1, $isSys, $result);
		}
		if (!$blockSys && $node['plus'] !== null) {
			$this->walk($node['plus'], $segments, $depth + 1, $isSys, $result);
		}
	}
}
