# Files, directories and providers

Load for file defaults/persistence, providers or directory discovery. Run `examples/sources.php` with a consumer autoloader on PHP 8.4+; see the baseline directory limitations before using PHP 8.3.

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

On 1.6.0 use absolute regular-file paths and avoid chained dangling symlinks or reliance on retaining group ownership (#24–#26). Persistent writes change the source representation and may replace comments; review ownership and deployment lifecycle before enabling them. Authority to modify a file is separate from permission to read it.

## Directories and ordering

`configDir($absoluteDirectory)` loads root-level `*.php` files. Zero-padded prefixes such as `10-default.php` and `20-local.php` make ordinary native-glob ordering explicit. Each file returns an array. Files/providers resolve lazily; inspect before executing them.

`configDir($dir, recursive: true)` and `config($dir)` select recursive mode. **In 1.6.0 it includes the root and one nested level only**, not arbitrary depth (#21). Glob metacharacters in the directory path are interpreted (#27). For deeper or glob-bearing layouts enumerate reviewed exact files in an explicit application order.

The baseline calls `mb_rtrim()` (PHP 8.4+) despite advertising PHP 8.3. Verify the runtime instead of relying on development polyfills (#20).

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

Laminas merges raw provider arrays before Config normalizes paths. Mixing dotted and nested forms for one setting can defeat later-source precedence on 1.6.0 (#23). Use a consistent nested-map representation across provider/file sources and verify overlapping values. Do not assume every numeric-key edge case agrees across aggregation entry points.

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

A cache path alone does not enable generation: merged config needs a truthy `config_cache_enabled` flag (#28). For directory caching put that flag in a discovered PHP file. It controls Laminas cache-file generation, not PSR-16 or persistent writes.

An existing cache file can be read without evaluating providers. Invalidate/rebuild the application's selected file when sources change; a stale file overrides provider changes. Check creation **and reuse** through a fresh wrapper. Subsequent `set()` calls do not persist to original providers or this cache file.
