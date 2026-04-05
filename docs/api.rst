API Reference
=============

This section maps the public surface of the package. Start here when you want to understand what each factory function or class is responsible for.

Factory Functions
-----------------

.. list-table:: Public factory functions
   :header-rows: 1

   * - Function
     - Purpose
     - Notes
   * - ``config(...$configs): ConfigInterface``
     - Best default entry point.
     - Accepts arrays, existing ``ConfigInterface`` objects, readable directory paths, and invokable provider class names.
   * - ``configCache(CacheInterface $cache, ...$configs): ConfigInterface``
     - Wraps any config source with PSR-16 caching.
     - Good for application-level cache integration.
   * - ``configDir(string $rootDirectory, bool $recursive = false, ?string $cachedConfigFile = null): ConfigInterface``
     - Loads PHP files from a directory.
     - Non-recursive by default.
   * - ``configProvider(iterable $providers, ?string $cachedConfigFile = null): ConfigInterface``
     - Aggregates provider classes with Laminas ConfigAggregator.
     - Useful for modular or package-driven configuration.

Related Pages
-------------

.. toctree::
   :maxdepth: 1

   api-interfaces
   api-helpers
   api-exceptions
