<?php

namespace Yew\Plugins\Topic;

/**
 * Subscription matching index (Trie) used by the Topic plugin.
 *
 * Replaces the former buildTrees() bitmask expansion so a published topic
 * resolves against subscriptions in O(levels) instead of O(2^levels).
 *
 * The Trie is maintained incrementally: insert() on subscribe, remove() on
 * unsubscribe. It mirrors the Topic plugin's $subscriptions store (the
 * authoritative index + persistence) but serves only fast matching.
 *
 * Node shape:
 *   'exact' => [segment => childNode]   literal segment branch
 *   'plus'  => childNode|null           single-level '+' branch
 *   'hash'  => [uid => uid]|null        '#' subscribers at this prefix
 *   'uids'  => [uid => uid]             exact subscribers ending here
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
		return ['exact' => [], 'plus' => null, 'hash' => null, 'uids' => []];
	}

	/**
	 * Insert a subscription filter (and its uid) into the Trie.
	 *
	 * '+' descends the single-level branch, '#' terminates the current node
	 * (multi-level wildcard), any other segment descends the literal branch.
	 *
	 * @param string $filter Subscription filter.
	 * @param string $uid    Subscriber unique id.
	 * @return void
	 */
	public function insert(string $filter, string $uid): void
	{
		if ($uid === '') {
			return;
		}

		$segments = explode('/', $filter);
		$node = &$this->root;
		foreach ($segments as $seg) {
			if ($seg === '#') {
				if ($node['hash'] === null) {
					$node['hash'] = [];
				}
				$node['hash'][$uid] = $uid;
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
		$node['uids'][$uid] = $uid;
	}

	/**
	 * Remove a uid from the Trie node that the filter resolves to.
	 *
	 * Empty nodes are left in place (harmless: only stored uids are matched).
	 *
	 * @param string $filter Subscription filter.
	 * @param string $uid    Subscriber unique id.
	 * @return void
	 */
	public function remove(string $filter, string $uid): void
	{
		if ($uid === '') {
			return;
		}

		$segments = explode('/', $filter);
		$node = &$this->root;
		foreach ($segments as $seg) {
			if ($seg === '#') {
				if (isset($node['hash'][$uid])) {
					unset($node['hash'][$uid]);
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
		if (isset($node['uids'][$uid])) {
			unset($node['uids'][$uid]);
		}
	}

	/**
	 * Resolve all subscriber uids for a published topic by walking the Trie.
	 *
	 * Cost is O(number of topic levels) per call, independent of the number of
	 * wildcards. A '$'-prefixed (MQTT System) topic never matches the root
	 * '+' / '#' branches, preserving the System Topic protection.
	 *
	 * @param string $topic Published topic.
	 * @return array Map of uid => uid (de-duplicated).
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
	 * Recursive Trie walk that collects matching uids.
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
			foreach ($node['hash'] as $uid) {
				$result[$uid] = $uid;
			}
		}

		$total = count($segments);
		if ($depth === $total && $node['uids'] !== []) {
			foreach ($node['uids'] as $uid) {
				$result[$uid] = $uid;
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
