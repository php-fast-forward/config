---
name: fast-forward-config
description: Implement or maintain application configuration with fast-forward/config in PHP. Use for composing arrays, files, directories and Laminas-style providers, dot-path lookup and mutation, persistent PHP files, PSR-16 caching, or config.* services exposed through PSR-11 and FastForward Container. Inspect the installed Composer version before selecting APIs.
license: MIT
compatibility: PHP 8.3+ and Composer; examples verified against fast-forward/config 1.6.0. Directory examples require PHP 8.4+ and mbstring; ConfigContainer also needs mbstring in that baseline. Optional FastForward Container integration uses 1.9.0. No host-specific tools or setup.
metadata:
  author: php-fast-forward
  api-baseline: "1.6.0"
---

# Fast Forward Config

Implement the application's configuration composition and return concrete changes with focused behavior checks. Activate when the project uses `fast-forward/config` or the user chose it. General dependency injection, library internals and skill installation have separate scopes.

## Inspect the consumer first

1. Read project instructions, Composer files, bootstrap and the relevant sources. PHP configuration executes code: inspect unfamiliar files/providers before loading them, preserve the application's secret mechanism, and avoid printing full configuration.
2. Run `composer show fast-forward/config` and `php --version`. If dependencies are absent, inspect `composer.lock` for the intended version; install only within task authority. A global skill copy does not upgrade a project.
3. The checked baseline is **1.6.0**. Native dot-path storage is released there; Laminas remains a runtime dependency and the deprecated misspelled compatibility class remains present in that release. Its removal in post-release source is not a published version. For other versions inspect installed source/tests and release notes before transferring version-sensitive behavior.
4. Check relevant current issues and the installed version before asserting a limitation or fix. Load [versions and known limitations](references/versions-and-limitations.md) when using caching, files, directories or PSR-11.
5. Locate the composition root and preserve source order, lifetime and persistence choices. Decide whether the task needs a fresh snapshot or mutation of an already resolved object.

## Select the entry point

| Need | API |
| --- | --- |
| Compose arrays, config objects, readable directories, PHP files or invokable provider class names | `FastForward\Config\config(...$sources)` |
| One in-memory array | `new ArrayConfig($array)` |
| Compose already adapted config objects | `new AggregateConfig(...$configs)` |
| One PHP file, defaults or explicit persistence | `configFile($absoluteFile, persistent: ..., defaultConfig: ...)` |
| Explicit non-recursive/recursive directory policy | `configDir($absoluteDirectory, recursive: ...)` |
| Laminas-style provider objects, callables or no-argument provider class names | `configProvider($providers)` |
| A controlled PSR-16 identity and mutation policy | `new CachedConfig($cache, $config, cacheKey: ..., persistent: ...)` |
| Expose the result through PSR-11 | `new FastForward\Config\Container\ConfigContainer($config)` |

`config()` creates a lazy aggregate even for one existing `ConfigInterface`; it does not preserve that object's identity. `AggregateConfig` accepts config objects, not raw arrays. A directory string passed to `config()` selects recursive loading. Invalid strings are not automatically empty configuration.

## Ordinary application setup

After the application's Composer dependencies are installed, create `bootstrap/config.php`:

```php
<?php

declare(strict_types=1);

use function FastForward\Config\config;

require dirname(__DIR__) . '/vendor/autoload.php';

return config(
    [
        'app' => ['env' => 'production', 'debug' => false],
        'jobs' => ['email', 'metrics'],
    ],
    [
        'app.debug' => true,
        'jobs' => ['email'],
    ],
);
```

Verify from the project root:

```sh
php -r '$c = require "bootstrap/config.php"; if ($c->get("app.debug") !== true || $c->get("jobs") !== ["email", "metrics", "email"]) { throw new RuntimeException("Configuration precedence failed"); }'
```

Aggregation recursively merges maps, concatenates sequential lists in source order, retains duplicates and lets later scalar values win. This differs from `set()`, which recursively replaces existing numeric indices: setting `jobs` to `['sms']` leaves `['sms', 'metrics', 'email']`. To replace the entire list deliberately, remove that key before setting the new list.

## Read and mutate deliberately

- `.` and `/` separate path segments. Leading, trailing and repeated separators preserve empty segments. An entirely empty `get/has/remove` path throws. Avoid empty keys: 1.6.0 `set('')` can store one that those lookup methods cannot read. There is no escaping syntax for literal separators.
- `has()` distinguishes an existing null from an absent key. `get($key, $default)` returns the default for either null or absence.
- `get()` wraps an all-string-key map, including an empty array, in a new `ArrayConfig`. Lists, sparse numeric arrays and mixed-key arrays stay arrays. A returned map is a separate value: mutate the owning config with its complete path.
- `set()`, `remove()` and ArrayAccess update the resolved object. They persist only through an explicitly persistent file/cache wrapper. `isset($config['key'])` follows `has()`, including present nulls. Appending through `$config[]` is unsupported.
- `toArray()` exports nested data; iteration yields flattened dot paths for leaves, including numeric indices. Empty arrays contribute no iteration entries, so iteration is not a lossless export.

Load [composition and path boundaries](references/composition-and-paths.md) for mixed keys, snapshots and full-list replacement. Its checked fixture is `examples/composition.php`.

## Load only the needed scenario

- For file defaults, trusted PHP paths, directory ordering, lazy providers and generated file caches, load [files and providers](references/files-and-providers.md), then run `examples/sources.php`.
- For PSR-16 identities, invalidation, lifetime and persistent mutations, load [caching](references/caching.md), then run `examples/caching.php`. On 1.6.0 prefer direct `CachedConfig` with an explicit portable key: the helper has no key argument and its default has known defects.
- For raw keys versus `config.*` identifiers and optional FastForward Container integration, load [container integration](references/containers.md), then run `examples/containers.php`.

Each program accepts the application's Composer autoloader:

```sh
php /path/to/fast-forward-config/examples/composition.php ./vendor/autoload.php
```

Examples use explicit comparisons and exceptions, independent of PHP's `assert()` setting. File examples create private temporary fixtures and clean up their own files; they do not edit application configuration. The cache fixture is educational, with no TTL implementation. Optional Container integration reports when its dependency is absent.

## Verify and report

Check the requested leaf value, source conflict, map/list shape, null/absence and mutation lifetime. For persistence, reload a new instance from its backend. For caches, check invalidation and the application's real backend; the bundled memory fixture does not prove race or failed-write handling. For integration, resolve both the raw key and prefixed service identifier.

Report installed versions, entry point, effective precedence, persistence/cache identity, changed files and checks actually run. Distinguish a successful PHP example, file installation, CLI listing and activation in a live agent session.
