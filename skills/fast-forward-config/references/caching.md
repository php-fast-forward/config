# PSR-16 caching

Load for cache identity, invalidation or mutation persistence. Run `examples/caching.php`; its strict memory cache is educational, with no TTL implementation.

## Explicit identity on 1.6.0

```php
use FastForward\Config\ArrayConfig;
use FastForward\Config\CachedConfig;
use Psr\SimpleCache\CacheInterface;

/** @var CacheInterface $cache */
$config = new CachedConfig(
    cache: $cache,
    defaultConfig: new ArrayConfig(['app' => ['env' => 'production']]),
    persistent: true,
    cacheKey: 'storefront.config.v1',
);
$config->set('app.env', 'staging');
```

Choose a stable, source-specific portable key (letters, numbers, underscores and dots; no reserved characters). Stay within the backend's supported length. Separate applications/deployments/tenants deliberately; do not put secrets in keys.

The 1.6.0 default is the source class: namespaced keys violate PSR-16 and same-class instances collide (#18). `configCache($cache, ...$sources)` offers no custom-key argument or persistent-write switch; prefer direct `CachedConfig` for this baseline. Do not patch vendor code silently.

## Lifetime and failures

On first resolution the wrapper checks an entry, populates a miss from its source, then reads it. A hit need not resolve the source. Normal calls keep a local snapshot; a fresh wrapper reads the backend again. There is no TTL argument: backend defaults apply. Configure lifetime there and invalidate/rebuild the selected identity when source configuration changes.

Without persistence, `set/remove` modify the local wrapper. With `persistent: true` they publish the mutated array to the backend; original files/providers/source configs remain unchanged. Reload a separate wrapper to verify persistence.

Eviction between `has/get`, a non-retaining backend or a failed write can pass null to ArrayConfig and raise TypeError on 1.6.0 (#19). Persistent writes also ignore a false backend result. An explicit key solves identity only. Use uncached `config(...)` if this failure mode is unacceptable until an installed version has a verified failure policy. Exercise the application's real backend and invalidation path.

Provider/directory `cachedConfigFile` is a separate Laminas feature; its `config_cache_enabled` flag does not apply to PSR-16.
