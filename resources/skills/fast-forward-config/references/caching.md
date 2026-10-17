# PSR-16 caching

Load for cache identity, invalidation, lifetime or mutation persistence. Run
`examples/caching.php`; its named memory fixture records TTLs and can decline
writes, but does not implement expiration or a real transport.

## Choose the identity deliberately

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

Choose a stable, source-specific portable key for cross-instance/request sharing.
Letters, numbers, underscores and dots avoid PSR-16 reserved characters; stay
within the backend's supported length. Separate applications/deployments/tenants
deliberately and do not put secrets in keys.

Omitting `cacheKey` generates a unique portable key for that wrapper. Its initial
and persistent writes use a **300-second TTL**. Explicit keys pass `null` TTL,
preserving the backend's configured default. There is no public TTL argument.

`configCache($cache, ...$sources)` composes sources and selects an isolated
non-persistent wrapper. It has no custom-key argument or persistent switch.
Use direct `CachedConfig` when those choices matter.

## Lifetime and failures

The first resolution reads once. An array hit bypasses source evaluation.
A missing or non-array entry resolves the source and attempts to cache it;
a false initial write still returns valid source data. Backend exceptions
propagate. Normal wrapper access reuses a local snapshot; a new wrapper reads
its selected backend identity again. Invalidate/rebuild a shared key when its
source changes.

Without persistence, `set/remove` modify only the local wrapper. With
`persistent: true`, they publish its mutated array; original files/providers
remain unchanged. A backend write returning false raises `RuntimeException`
while retaining the local mutation. Check recovery and reload through a separate
wrapper before claiming publication succeeded.

Provider/directory `cachedConfigFile` is a separate Laminas feature.
Its `config_cache_enabled` flag does not control PSR-16 caching.
