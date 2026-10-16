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
   * - ``PhpFileConfig``
     - Lazy loader for a single PHP file with optional persistence.
     - Your config is stored in a single PHP file, or you want write-through changes.
   * - ``LaminasConfigAggregatorConfig``
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
- ``RecursiveDirectoryConfig`` scans nested subdirectories at every depth.
- Both validate that the directory exists and is readable.
- Both support an optional cache file path through Laminas ConfigAggregator.

Generating that file also requires a truthy ``config_cache_enabled`` entry in a
loaded PHP configuration array and an existing, writable parent directory. Keep
the cache outside the scanned source directory. An existing cache file is reused
before loading source files. See :doc:`advanced/caching`.

Directory paths are treated literally, including characters such as brackets and braces. The protected ``PATTERN`` constant controls filename selection; overriding it continues to use the existing glob and brace-order semantics.

Selected files are included through their resolved absolute paths. Relative directory roots therefore cannot be redirected through PHP's ``include_path`` or configuration files that change the working directory during loading.

Discovery stays lazy. Root files are loaded before files one directory below, then files two directories below, and so on. Within each level, directories are ordered by their paths and the filename pattern determines the order inside each directory. The default patterns therefore produce deterministic path ordering while preserving later-value overrides and sequential-list concatenation.

Child directory symlinks are not followed, so aliases and recursive cycles do not duplicate traversal. Hidden child directories are skipped. File symlinks may be loaded, but every resolved file is included only once; its first discovered path determines its merge position. A root directory explicitly supplied by the caller may itself be a symlink.

Recursive traversal skips child directories that are unreadable or disappear before they can be opened. Accessible configuration still loads. Root read failures and unrelated filesystem or configuration-file errors remain visible.

Existing generated cache files bypass discovery. Invalidate or rebuild them after upgrading if they were created while deeper configuration files were omitted.

PhpFileConfig
-------------

``PhpFileConfig`` loads configuration from a single PHP file returning an array.

Important constructor options:

- ``file``: path to the PHP configuration file.
- ``persistent``: whether ``set()`` and ``remove()`` should write changes back to the PHP file.
- ``defaultConfig``: optional default configuration used when the file does not yet exist.

Relative inputs are anchored to an absolute location on first use, independently of PHP's
``include_path``. Later working-directory changes do not move the instance to another file.
Only a valid URI scheme at the start of the input selects a stream path; a relative filesystem
path such as ``nested/file://settings.php`` is still anchored to its initial directory.
Each persistent mutation refreshes PHP's path caches before following the current symlink
chain from that location, including directory links changed by another process. It uses the
same resolved target for its lock, fresh read, and atomic replacement. Symlink chains are
followed to the final target, including a missing target created from ``defaultConfig``.
This also applies when a parent-directory link points to a directory that does not yet exist.
Cyclic or excessively long chains raise
``FastForward\\Config\\Exception\\InvalidArgumentException`` before any link is replaced.
On POSIX, symlink targets are literal filesystem paths: ``://`` in a relative target does
not select a stream wrapper. Unavailable PHP functions required to resolve a path also raise
``InvalidArgumentException`` before any persistent change.

Atomic replacements preserve existing permission bits and, on POSIX systems, the existing
file's group. If the group or permissions cannot be applied to the temporary file, including
when required metadata functions are disabled, the mutation raises ``InvalidArgumentException``
and leaves the original file intact. New files use
``0666 & ~umask()`` and the filesystem's default creation group. Windows does not attempt POSIX
group reassignment; its native permission behavior applies.

On Windows, single-separator rooted paths such as ``\\settings.php`` or ``/settings.php``
are anchored to the initial drive or UNC volume on first use. Fully qualified drive,
UNC, and DOS device paths retain their supplied root.

Drive-relative paths such as ``C:settings.php`` use native PHP resolution when available.
Supported inputs are anchored to their drive's directory while retaining their relative
suffix, so later symlink retargeting still applies.
If such a path cannot be resolved, including an existing path unsupported by native PHP, it raises
``InvalidArgumentException`` before creating directories, locks, or files. Use a
fully qualified path such as ``C:\\app\\settings.php`` when creating a new target.

LaminasConfigAggregatorConfig
-----------------------------

This class is the direct bridge around ``Laminas\\ConfigAggregator\\ConfigAggregator``. Use it when you want provider-based config aggregation without going through the helper functions.

``cachedConfigFile`` selects a cache file. If it already exists, it is loaded
before providers execute. Generating a missing cache file also requires a truthy
``ConfigAggregator::ENABLE_CACHE`` (``config_cache_enabled``) value in merged
provider output and an existing, writable parent directory. Passing ``null``
bypasses file caching; a false enabling flag alone does not bypass an existing file.

.. note::
   The deprecated spelling ``LamiasConfigAggregatorConfig`` has been removed. Replace imports, type declarations, and instantiations with ``LaminasConfigAggregatorConfig``; there is no autoload alias fallback.

CachedConfig
------------

``CachedConfig`` wraps another ``ConfigInterface`` and stores the resolved array in a PSR-16 cache backend.

Important constructor options:

- ``cache``: the PSR-16 cache backend.
- ``defaultConfig``: the wrapped config source.
- ``persistent``: whether ``set()`` and ``remove()`` should write back to the cache backend.
- ``cacheKey``: optional application-owned key for cross-instance reuse with the backend's default lifetime. Without one, each wrapper uses its own portable PSR-16 key and every write expires after 300 seconds.

Cache misses and non-array entries fall back to the source without requiring a successful cache write or readback. Cache hits leave the source unresolved. Persistent mutations raise ``RuntimeException`` when the backend returns ``false`` from ``set()``; the local mutation is retained. See :doc:`advanced/caching` for migration and invalidation details.

ConfigContainer
---------------

``ConfigContainer`` adapts a config object to PSR-11. It resolves these identifiers:

- ``config`` returns the wrapped config object.
- ``FastForward\\Config\\ConfigInterface`` returns the wrapped config object.
- The concrete config class name returns the wrapped config object.
- ``config.some.key`` returns a single configuration value.
- ``FastForward\\Config\\Container\\ConfigContainer`` returns the container wrapper itself.

Unknown identifiers raise ``ContainerNotFoundException``.
