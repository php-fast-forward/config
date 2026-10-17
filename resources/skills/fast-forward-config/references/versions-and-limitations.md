# Supported versions and boundaries

Load for version selection, migration or failure policy. This skill supports
**`fast-forward/config ^1.7`**, starting at **1.7.0**, on PHP **8.3+**. Inspect
`composer show fast-forward/config`, the lockfile and installed source before
applying it. Earlier versions require their matching documentation and are
outside this procedure. Do not silently upgrade an application to use the skill.

Config does not require mbstring. Its production dependencies include
`brick/varexporter ^0.5 || ^0.6 || ^0.7`, `laminas/laminas-config-aggregator ^1.13`,
`psr/container ^1.0 || ^2.0` and `psr/simple-cache ^3.0`. The optional full-container
example supports `fast-forward/container ^1.9`; it is not a Config requirement.
Check that companion's own version and guidance before selecting integration APIs.

## Migration from 1.6

- Replace every `LamiasConfigAggregatorConfig` reference with
  `LaminasConfigAggregatorConfig`. The misspelled class was deprecated in 1.5;
  it has been removed without an autoload alias.
- Default PSR-16 keys isolate wrappers and expire after 300 seconds.
  Cross-instance/request sharing requires an explicit stable portable key.
  Old source-class-name cache entries are not reused.
- Container identifiers require the exact nonempty `config.` prefix.
  Unknown or empty suffixes follow the PSR-11 not-found contract.
- Recursive directories load every visible depth in depth-then-path order,
  treat the directory path literally and do not traverse child directory symlinks.
- Provider/file dot and slash paths normalize before cross-source merging,
  so later dotted and nested forms share the same scalar precedence.

The native dot-path storage introduced in 1.6 does not remove Laminas provider
integration or Brick's object-capable file export. Do not replace these runtime
dependencies as an ordinary consumer task.

## Boundaries to verify in the application

PHP files and providers execute code. Defaults cover missing files, not invalid
existing files. File persistence rewrites executable PHP and may remove comments;
use application-controlled paths, deployment permissions and persistable values.
Only explicitly persistent wrappers publish mutations.

Maps returned by `get()` are independent values. Lists, sparse numeric collections
and mixed arrays keep their array boundaries. Empty lookup/removal paths are
invalid; literal dots/slashes have no escaping syntax. Flattened iteration omits
empty arrays, so use `toArray()` for an export.

PSR-16 entries must contain arrays. A miss or non-array value resolves the source;
an initial `set()` returning false does not discard valid source data. Backend
exceptions propagate. Persistent mutation with a declined write raises
`RuntimeException` and retains the local mutation. Decide recovery in the
application and check the actual backend; the educational fixture does not prove
expiration, concurrency or transport availability.

Generated provider/directory file caches require a truthy
`config_cache_enabled` flag and a writable existing parent. A path alone does not
enable generation. A preexisting cache can bypass providers until the application
invalidates it. This is separate from PSR-16 caching and persistent source files.
