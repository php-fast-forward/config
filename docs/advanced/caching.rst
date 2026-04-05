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

On the first resolution, the merged configuration is stored in the cache. Later resolutions read from the same cache key.

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
       cacheKey: 'app-config',
   );

.. warning::

   ``configCache()`` creates ``CachedConfig`` with the default ``persistent: false`` behavior. That means ``set()`` and ``remove()`` update the resolved in-memory config, but they do not write the modified result back to the cache backend unless you instantiate ``CachedConfig`` yourself with ``persistent: true``.

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
