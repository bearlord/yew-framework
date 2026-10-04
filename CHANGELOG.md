# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.1] - 2026-10-04

### Fixed

- **Actor: recovery never actually ran on process restart or lazy lookup.** `recoverLocalActors()`
  and the lazy path in `ActorManager::getActor()` instantiated actors with `$isCreated = true`, so the
  constructor called `addActor()` while a row for that name was already present in the shared
  `actorTable`. `addActor()` rejects duplicates with `Has same actor name`, and it runs *before*
  `init()` / `recovery()`, so the actor was left uninitialized and unroutable. Both paths now build
  the instance without registering it, drop the stale row, and register once.
- **Actor: failover spawn was reported as failed even when it succeeded.**
  `ActorFailover::spawnIfOwned()` called `addActor()` a second time after the constructor had already
  registered the actor, throwing and returning `false` with a misleading warning. Now registered once.
- **Actor: mailbox coroutine and actor instance leaked on destroy / restart.** The consumer coroutine
  started in `init()` only exits when `Channel::pop()` returns `false`, which requires the channel to
  be closed. Neither `destroy()` nor `restartActor()` closed it, so the coroutine blocked forever and —
  because the closure binds `$this` — kept the whole actor instance alive. Added `closeMailbox()`,
  called from both paths.
- **Actor: `after()` timer ids accumulated forever.** `Swoole\Timer::after()` is one-shot, but its id
  was kept in `$timerIds` after firing. Actors scheduling many delays grew the array indefinitely. The
  id is now removed before the callback runs.
- **Actor: `TypeError` when a persisted snapshot held a non-array state.** `recovery()` assigned the
  snapshot state straight into the typed `array $data` property. It now validates with `is_array()`,
  keeps the existing state and logs a warning when the snapshot is corrupt or foreign.
- **Actor: `recovery()` ran twice per restore.** `recoverLocalActors()` and the lazy path called
  `recovery()` explicitly right after a constructor that had already run it, re-reading the snapshot
  and replaying the whole event log for nothing.

### Added

- **Actor: `onRecovered()` lifecycle hook.** Invoked once durable state is fully loaded, i.e. after
  `recovery()` has replayed the snapshot and the event log. Unlike `init()` — which runs *before*
  `recovery()` and therefore still sees an empty `$this->data` — this hook can safely read the restored
  state, which is what you need to re-arm work derived from it (e.g. re-scheduling a one-shot delay
  from a persisted expiry timestamp instead of a raw interval). Also invoked for freshly created
  actors, so keep implementations idempotent.
- **`BaseActor` base class.** The framework plumbing that a business subclass rarely touches moved out
  of `Actor`: property declarations, the constructor, strategy resolution, the supervision tree,
  mailbox dispatch, and multicast forwarding. `Actor` keeps the business-facing surface — lifecycle
  hooks, persistence, timers, mailbox. Business code still extends `Actor` and is unaffected.

### Changed

- `closeMailbox()` was added to the `ActorIpcProxy` reserved-method list. It must stay callable only
  in-process: invoking it over IPC would close the remote actor's mailbox without cleaning up the
  registry.
- **Mongodb plugin:** removed speculative/inferential comments and converted the remaining ones to
  English. No functional change.

### Upgrade notes

- No changes required in application code. `Actor` keeps every method it had; the new `BaseActor`
  class is internal and must not be extended directly.
- Recovery is the area that was silently broken, so please re-verify it after upgrading: restarting an
  actor process should now log `recovered N actor(s) on startup: ...`, and the previous
  `failed to recover local actor` / `lazy recovery ... failed` warnings should be gone.
- Long-running processes that create and destroy actors repeatedly should no longer accumulate
  coroutines; worth confirming with a create/destroy loop if you relied on actor churn.
