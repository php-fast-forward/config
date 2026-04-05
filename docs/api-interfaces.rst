Interfaces And Classes
======================

The package exposes a small set of public types. Most users touch only the factory functions and ``ConfigInterface``, but the concrete classes are available when you need explicit behavior.

.. list-table:: Public types at a glance
   :header-rows: 1

   * - Type
     - Responsibility
     - Use when
   * - ``ConfigInterface``
     - Common contract for reading, writing, iterating, and exporting configuration.
     - You want to depend on an abstraction.
   * - ``ArrayConfig``
     - In-memory config storage with dot-notation support.
     - Your config already exists as an array or you are writing tests.
   * - ``AggregateConfig``
     - Lazy merger for several ``ConfigInterface`` sources.
     - You want one config built from many existing config objects.
   * - ``DirectoryConfig``
     - Lazy loader for top-level PHP files in one directory.
     - Your config lives in one directory and you do not want recursion.
   * - ``RecursiveDirectoryConfig``
     - Lazy loader for PHP files in a directory tree.
     - Your config is split across nested folders.
   * - ``LamiasConfigAggregatorConfig``
     - Bridge to Laminas ConfigAggregator.
     - You want provider-based aggregation directly.
   * - ``CachedConfig``
     - PSR-16 cache wrapper around another config source.
     - You need cache integration or custom cache-key behavior.
   * - ``ConfigContainer``
     - PSR-11 container wrapper for config access.
     - Another part of your system expects ``ContainerInterface``.

ConfigInterface
---------------

``ConfigInterface`` defines the shared behavior across all config implementations:

- ``has(string $key): bool``
- ``get(string $key, mixed $default = null): mixed``
- ``set(array|ConfigInterface|string $key, mixed $value = null): void``
- ``remove(string $key): void``
- ``toArray(): array``

It also extends ``IteratorAggregate`` and ``ArrayAccess``, so config objects can be iterated and accessed like arrays.

ArrayConfig
-----------

``ArrayConfig`` is the simplest concrete implementation:

- It normalizes associative arrays so dot-notated keys become nested arrays.
- ``get()`` returns another ``ArrayConfig`` for associative subtrees.
- Sequential lists remain regular PHP arrays.
- Iteration yields flattened dot-notated keys.

AggregateConfig
---------------

``AggregateConfig`` merges several ``ConfigInterface`` instances into one lazily resolved config. Later sources override earlier values for the same key while preserving unrelated nested values.

DirectoryConfig And RecursiveDirectoryConfig
--------------------------------------------

These classes load PHP files from disk:

- ``DirectoryConfig`` scans only the top level of the target directory.
- ``RecursiveDirectoryConfig`` also scans nested subdirectories.
- Both validate that the directory exists and is readable.
- Both support an optional cache file path through Laminas ConfigAggregator.

LamiasConfigAggregatorConfig
----------------------------

This class is the direct bridge around ``Laminas\\ConfigAggregator\\ConfigAggregator``. Use it when you want provider-based config aggregation without going through the helper functions.

The public class name is exactly ``LamiasConfigAggregatorConfig`` because that is what the package exports today.

CachedConfig
------------

``CachedConfig`` wraps another ``ConfigInterface`` and stores the resolved array in a PSR-16 cache backend.

Important constructor options:

- ``cache``: the PSR-16 cache backend.
- ``defaultConfig``: the wrapped config source.
- ``persistent``: whether ``set()`` and ``remove()`` should write back to the cache backend.
- ``cacheKey``: optional custom cache key.

ConfigContainer
---------------

``ConfigContainer`` adapts a config object to PSR-11. It resolves these identifiers:

- ``config`` returns the wrapped config object.
- ``FastForward\\Config\\ConfigInterface`` returns the wrapped config object.
- The concrete config class name returns the wrapped config object.
- ``config.some.key`` returns a single configuration value.
- ``FastForward\\Config\\Container\\ConfigContainer`` returns the container wrapper itself.

Unknown identifiers raise ``ContainerNotFoundException``.
