# Files, directories and providers

Load for file defaults/persistence, providers or directory discovery. Run `examples/sources.php` with the application's Composer autoloader on PHP 8.3+.

## One PHP file

Use an application-controlled **absolute path** to PHP returning an array:

```php
use function FastForward\Config\configFile;

$config = configFile(
    __DIR__ . '/../var/config/runtime.php',
    persistent: true,
    defaultConfig: ['app' => ['env' => 'production']],
);
$config->set('app.env', 'staging');
```

Construction is lazy. A missing file without defaults fails on resolution. Defaults apply only when the file is absent, not when existing PHP returns the wrong type. Reading defaults does not create a file, even with persistence enabled. The first persistent mutation creates the file and missing parent directories. Without `persistent: true` mutations affect only the in-memory instance.

Persistent mutation reloads the source under a sidecar lock and atomically replaces its target. The caller needs writable file/parent permissions. Generated files are executable PHP and must remain application-controlled. Supported values are exported through Brick VarExporter; do not claim arbitrary objects, closures or resources are universally persistable. Reload the actual value types you intend to store.

Prefer explicit absolute file paths, then verify permissions and deployment ownership for persistent writes. Persistent writes change the source representation and may replace comments; review ownership and deployment lifecycle before enabling them. Authority to modify a file is separate from permission to read it.

## Directories and ordering

`configDir($absoluteDirectory)` loads root-level `*.php` files. Zero-padded prefixes such as `10-default.php` and `20-local.php` make ordinary native-glob ordering explicit. Each file returns an array. Files/providers resolve lazily; inspect before executing them.

`configDir($dir, recursive: true)` and `config($dir)` select all-depth recursive
mode. Files load by depth, then directory/path order; filename-pattern overrides
remain effective. Root path metacharacters are literal. Canonical file identities
are included once. Child directory symlinks are not traversed, hidden child
directories are skipped, and inaccessible/disappeared children do not prevent
loading accessible siblings. An unreadable root remains an error.

For a business-specific order, enumerate reviewed files explicitly with
`config(...$absoluteFiles)`. Directory loading uses core PHP functions; no
mbstring extension or PHP 8.4 polyfill is required.

## Providers

```php
use function FastForward\Config\configProvider;

$config = configProvider([
    static fn(): array => ['app' => ['env' => 'production']],
    static fn(): array => ['app' => ['env' => 'testing']],
]);
if ($config->get('app.env') !== 'testing') {
    throw new RuntimeException('Provider precedence failed');
}
```

Use `LaminasConfigAggregatorConfig` for direct construction. Invokable class names need no-argument construction; use objects/callables to supply dependencies. `config(...)` accepts provider class names, not arbitrary closures; wrap callables with `configProvider([...])`.

Each provider or generated file-provider array normalizes dot/slash paths before
Laminas merges sources. Later scalar values win even when one source uses
`app.env` and another uses nested `app => env`. Sequential lists retain ordered
duplicates. Keep config maps separate from sparse/mixed collections and verify
overlapping values rather than assuming every numeric-key edge case appends.

## Generated PHP file caches

```php
use Laminas\ConfigAggregator\ConfigAggregator;
use function FastForward\Config\configProvider;

// The parent directory already exists and is writable.
$config = configProvider(
    [
        static fn(): array => [
            ConfigAggregator::ENABLE_CACHE => true,
            'app' => ['env' => 'production'],
        ],
    ],
    cachedConfigFile: __DIR__ . '/../var/cache/providers.php',
);
$config->toArray(); // Resolves and writes the enabled cache.
```

A cache path alone does not enable generation: merged config needs a truthy `config_cache_enabled` flag. For directory caching put that flag in a discovered PHP file. It controls Laminas cache-file generation, not PSR-16 or persistent writes.

An existing cache file can be read without evaluating providers. Invalidate/rebuild the application's selected file when sources change; a stale file overrides provider changes. Check creation **and reuse** through a fresh wrapper. Subsequent `set()` calls do not persist to original providers or this cache file.
