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

``cachedConfigFile`` selects the PHP cache file used by Laminas ConfigAggregator.
It does not enable cache generation on its own. The merged configuration must also
contain a truthy ``ConfigAggregator::ENABLE_CACHE`` (``config_cache_enabled``) value.

Create the cache file's parent directory before resolving the configuration, and
make it writable by the PHP process. Keep generated cache files outside the
configuration directory being scanned. With Composer's autoloader already loaded,
this complete provider example creates the parent directory and enables generation:

.. code-block:: php

   use Laminas\ConfigAggregator\ConfigAggregator;
   use function FastForward\Config\configProvider;

   $cacheDirectory = __DIR__ . '/var/cache';
   if (!is_dir($cacheDirectory)) {
       mkdir($cacheDirectory, 0775, true);
   }

   $config = configProvider(
       [static fn(): array => [
           ConfigAggregator::ENABLE_CACHE => true,
           'app' => ['name' => 'Example App'],
       ]],
       cachedConfigFile: $cacheDirectory . '/providers.php',
   );

   $data = $config->toArray(); // Resolves providers and writes the cache file.

For directory aggregation, put the enabling flag in a PHP file loaded from the
configuration directory, for example ``config/cache.php``:

.. code-block:: php

   <?php

   use Laminas\ConfigAggregator\ConfigAggregator;

   return [ConfigAggregator::ENABLE_CACHE => true];

Then resolve the directory with an existing, writable cache parent:

.. code-block:: php

   use function FastForward\Config\configDir;

   $cacheDirectory = __DIR__ . '/var/cache';
   if (!is_dir($cacheDirectory)) {
       mkdir($cacheDirectory, 0775, true);
   }

   $config = configDir(
       __DIR__ . '/config',
       recursive: true,
       cachedConfigFile: $cacheDirectory . '/config.php',
   );

   $data = $config->toArray();

Cache Reading And Disabling Generation
----------------------------------------

When the selected cache file already exists, Laminas loads it before executing
providers. A missing or false ``ENABLE_CACHE`` value prevents generation only
when resolving providers; it does not bypass an existing cache file. To ignore
an existing file, pass ``cachedConfigFile: null``. Remove the old cache file
before rebuilding it from changed providers or directory contents.

For example, this resolves providers without creating a missing cache file:

.. code-block:: php

   use Laminas\ConfigAggregator\ConfigAggregator;
   use function FastForward\Config\configProvider;

   $config = configProvider(
       [static fn(): array => [
           ConfigAggregator::ENABLE_CACHE => false,
           'app' => ['name' => 'Example App'],
       ]],
       cachedConfigFile: __DIR__ . '/var/cache/disabled.php',
   );

   $data = $config->toArray();

The Laminas flag does not control PSR-16 ``configCache()`` or ``CachedConfig``.

Choose The Right Strategy
-------------------------

- Use ``configCache()`` when you already have a PSR-16 cache service.
- Use ``cachedConfigFile`` with ``ENABLE_CACHE`` when you want a generated file for directory or provider aggregation.
- Clear or rebuild your chosen cache when configuration sources change between deployments.
