Caching
=======

FastForward Config supports two different caching approaches. They solve related problems, but they are not the same feature.

.. list-table:: Cache options
   :header-rows: 1

   * - Option
     - Best for
     - Storage
   * - ``configCache()`` or ``CachedConfig``
     - Caching the final merged config behind any PSR-16 backend.
     - Your cache implementation.
   * - ``cachedConfigFile`` on ``configDir()`` or ``configProvider()``
     - Reusing a generated PHP cache file for directory or provider aggregation.
     - A file on disk handled by Laminas ConfigAggregator.

Caching With PSR-16
-------------------

Use ``configCache()`` when your application already has a PSR-16 cache backend:

.. code-block:: php

   use Psr\SimpleCache\CacheInterface;
   use function FastForward\Config\configCache;

   /** @var CacheInterface $cache */
   $config = configCache($cache, __DIR__ . '/config');

On the first resolution, the merged configuration is returned and a cache write is attempted. A cache miss, an expired or evicted entry, a non-array entry, or a backend that declines to store the value falls back to the source. A successful cache hit does not resolve the source.

Default Keys And Source Isolation
---------------------------------

Without an explicit ``cacheKey``, each ``CachedConfig`` wrapper generates its own random key using only portable PSR-16 characters and fewer than 64 characters. The key stays the same for that wrapper's lifetime. Separate wrappers, including separate calls to ``configCache()``, use isolated entries even when their sources have the same PHP class.

Default keys are not stable across wrapper reconstruction or application requests. This isolation avoids inspecting, executing, or serializing an arbitrary lazy source merely to identify it. To reuse a cached configuration between instances, workers, or requests, instantiate ``CachedConfig`` with the same explicit application-owned key, as shown below. Prefer an explicit key in long-lived shared pools: default entries are not reused after their wrapper is discarded, and their retention follows the backend's default lifetime.

Using A Custom Cache Key Or Persistent Writes
---------------------------------------------

Instantiate ``CachedConfig`` directly when you need more control:

.. code-block:: php

   use FastForward\Config\CachedConfig;
   use Psr\SimpleCache\CacheInterface;
   use function FastForward\Config\configDir;

   /** @var CacheInterface $cache */
   $config = new CachedConfig(
       cache: $cache,
       defaultConfig: configDir(__DIR__ . '/config', recursive: true),
       persistent: true,
       cacheKey: 'app-config-v2',
   );

.. warning::

   ``configCache()`` creates ``CachedConfig`` with the default ``persistent: false`` behavior. That means ``set()`` and ``remove()`` update the resolved in-memory config, but they do not write the modified result back to the cache backend unless you instantiate ``CachedConfig`` yourself with ``persistent: true``.

With ``persistent: true``, a backend ``set()`` result of ``false`` during an explicit ``set()`` or ``remove()`` raises ``RuntimeException``. The local mutation remains available on that wrapper; the backend may still contain the previous value. Handle the exception and retry or invalidate the entry according to your application policy. Exceptions raised by the backend propagate to the caller. Initial cache population remains best effort, even in persistent mode, so a declined initial write does not prevent reading valid source data.

Migration And Invalidation
--------------------------

Earlier versions used the source's class name as the default key. Those keys could contain characters forbidden by PSR-16 and could mix data from unrelated sources of the same class. New default keys do not read those old entries. Remove obsolete entries through your backend's maintenance tools when appropriate.

Existing explicit keys are preserved without rewriting or hashing. Applications that relied on default cross-instance reuse must now supply an explicit ``cacheKey``. Use a separate key for each source, environment, or tenant that needs isolation. Update that key or invalidate its entry when configuration sources change; cached arrays are not automatically checked against the source.

Caching Provider Or Directory Aggregation To A File
---------------------------------------------------

If you want Laminas ConfigAggregator to write a cache file, use ``cachedConfigFile``:

.. code-block:: php

   use function FastForward\Config\configDir;

   $config = configDir(
       __DIR__ . '/config',
       recursive: true,
       cachedConfigFile: __DIR__ . '/../var/cache/config.php',
   );

Choose The Right Strategy
-------------------------

- Use ``configCache()`` when you already have a PSR-16 cache service.
- Use ``cachedConfigFile`` when you want an explicit generated file for directory or provider aggregation.
- Clear or rebuild your chosen cache when configuration sources change between deployments.
