Compatibility
=============

.. list-table:: Supported capabilities
   :header-rows: 1

   * - Capability
     - Status
     - Notes
   * - PHP runtime
     - Supported
     - Requires PHP ``8.3`` or newer.
   * - PSR-11 containers
     - Supported
     - ``ConfigContainer`` implements ``Psr\Container\ContainerInterface``.
   * - PSR-16 caches
     - Supported
     - ``configCache()`` and ``CachedConfig`` accept any ``Psr\SimpleCache\CacheInterface`` implementation.
   * - Laminas ConfigAggregator providers
     - Supported
     - ``configProvider()`` and ``LamiasConfigAggregatorConfig`` integrate with Laminas provider workflows.
   * - PHP files returning arrays
     - Supported
     - ``DirectoryConfig`` and ``RecursiveDirectoryConfig`` load ``*.php`` files from disk.
   * - JSON, YAML, XML loaders
     - Not built in
     - Wrap those formats in your own provider or convert them to arrays before passing them in.

Practical Notes
---------------

- Directory loading expects readable directories and PHP files that return arrays.
- ``config()`` auto-detects readable directories and invokable provider class names.
- The package is especially convenient for users migrating from array-based config files or Laminas-style provider classes.
