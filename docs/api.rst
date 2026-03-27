API Reference
=============

This section documents all main API functions and classes. For full PHPDoc and source, see the `GitHub repository <https://github.com/php-fast-forward/config>`_ and the `Live Coverage Report <../public/coverage/index.html>`_.

.. _api:

.. list-table:: Main API Functions
   :header-rows: 1

   * - Function
     - Description
   * - ``config(...$configs): ConfigInterface``
     - Aggregate configs from arrays, directories, or providers. Accepts arrays, directory paths, or provider class names.
   * - ``configCache(CacheInterface $cache, ...$configs): ConfigInterface``
     - Wrap config with PSR-16 cache. Useful for performance in production.
   * - ``configDir(string $dir, bool $recursive = false, ?string $cache = null): ConfigInterface``
     - Load config from a directory (optionally recursive). Each PHP file must return an array.
   * - ``configProvider(iterable $providers, ?string $cache = null): ConfigInterface``
     - Load config from Laminas-style providers (classes with __invoke()).

See also:
- :doc:`api-interfaces`
- `README API Summary <https://github.com/php-fast-forward/config#-api-summary>`_

.. toctree::
   :maxdepth: 1

   api-interfaces
