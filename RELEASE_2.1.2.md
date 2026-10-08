# Yew Framework 2.1.2

Patch release fixing PHP 8.2 compatibility issues in annotation scanning and master-process IPC.

### Highlights

- **PHP 8 attribute scanning works again.** The scanner used `ReflectionAttribute::newInstance()`, which spreads named arguments and fails against Doctrine's `final Annotation::__construct(array $data)`. Attributes such as `#[RestController]` and `#[GetMapping(...)]` now resolve correctly alongside docblock annotations, and a malformed attribute is skipped with a warning instead of aborting the scan.
- **Master → custom process IPC no longer crashes the worker.** The master process has `processId = -1`, and packing it with `pack('N', ...)` overflowed, so the receiver could not resolve the source process and fatalled. The frame now encodes the negative id correctly and the source process is resolved on the receiving side.

### Fixed

- AnnotationsScan: PHP 8 attribute support broken for Doctrine `Annotation` subclasses
- Process: IPC message from the master process crashed the worker (`processId = -1` overflowed `pack('N')`, source process unresolved)

### Upgrading

No application changes required.

If you run on **PHP 8.2**, upgrading is mandatory — earlier 2.1.x releases fatal during annotation scanning or when the master process sends a message to a custom process.

Full details: [CHANGELOG.md](https://github.com/bearlord/yew-framework/blob/master/CHANGELOG.md)
