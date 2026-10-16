# Versions and known limitations

Load for version selection, failures or workaround removal. Observed against **Config 1.6.0**, commit `3282f877808a23382cd41f4ed30946fcc10ec515`. Recheck the installed source and current issue/release state; pending work does not redefine this baseline.

| Release | Boundary |
| --- | --- |
| 1.1.0 | ConfigContainer introduced; prefix/alias fixes followed in 1.1.3/1.1.4. |
| 1.2.0 | Custom CachedConfig keys and persistent set updates. |
| 1.3.0 | ConfigHelper and flattened iteration. Directory strings already worked in the initial release; do not infer introduction from a misattributed changelog bullet. |
| 1.4.0 | ArrayAccess and remove(). |
| 1.5.0 | Correctly spelled LaminasConfigAggregatorConfig, deprecated compatibility name, single-file configuration support. |
| 1.5.1 | Source-aggregation list concatenation and sparse/mixed array boundaries fixed. |
| 1.6.0 | Native dot-path storage replaces dflydev/dot-access-data; empty lookup/removal paths use this library's InvalidArgumentException. |

This skill was authored **after 1.6.0**, whose archive does not contain it. Install from a later skill-bearing release or a reviewed revision containing the directory. Its examples nevertheless target the published 1.6.0 API.

## Released baseline and subsequent work

| Issue | 1.6.0 boundary |
| --- | --- |
| [#16](https://github.com/php-fast-forward/config/issues/16) | `LamiasConfigAggregatorConfig` exists in published 1.6.0; use `LaminasConfigAggregatorConfig` in new code. The compatibility class was removed in post-release source (PR #32), which does not make that removal part of 1.6.0. |
| [#18](https://github.com/php-fast-forward/config/issues/18) | Default PSR-16 keys contain reserved backslashes and collide by source class. Supply a stable, portable key directly to CachedConfig. |
| [#19](https://github.com/php-fast-forward/config/issues/19) | Eviction/non-retaining/failed-write paths can throw TypeError; persistent publication ignores failed set results. Explicit keys do not solve this. |
| [#20](https://github.com/php-fast-forward/config/issues/20) | Directories require mb_rtrim (PHP 8.4+); ConfigContainer uses undeclared mbstring functions. Development polyfills can mask runtime failure. |
| [#21](https://github.com/php-fast-forward/config/issues/21) | Recursion stops after one nested level; enumerate deeper files explicitly when needed. |
| [#22](https://github.com/php-fast-forward/config/issues/22) | Container own-class has/get mismatch and loose/empty prefix handling. Use documented nonempty config.* identifiers; prefixes are not access control. |
| [#23](https://github.com/php-fast-forward/config/issues/23) | Mixed dotted/nested provider/file forms can defeat later precedence. Keep a consistent nested representation and check conflicts. |
| [#24](https://github.com/php-fast-forward/config/issues/24) | Relative file loads can select include_path matches. Use absolute application-controlled file paths. |
| [#25](https://github.com/php-fast-forward/config/issues/25) | Persistent writes through dangling symlink chains can replace an intermediate link. Use ordinary target files in baseline examples. |
| [#26](https://github.com/php-fast-forward/config/issues/26) | Atomic persistent replacement does not retain existing group ownership. Verify deployment ownership needs. |
| [#27](https://github.com/php-fast-forward/config/issues/27) | Directory glob metacharacters are interpreted; avoid such roots or enumerate exact files. |
| [#28](https://github.com/php-fast-forward/config/issues/28) | A file-cache path alone does not enable generation: include ConfigAggregator::ENABLE_CACHE => true and a writable existing parent. |

Laminas ConfigAggregator and Brick VarExporter remain production dependencies. The native path refactor does not remove provider integration or object-capable file export. Do not introduce a substitute implementation as ordinary consumer guidance.
