Interfaces & Classes
====================

This library is built around a set of interfaces and classes that make configuration flexible and extensible.

.. list-table:: Main Interfaces & Classes
   :header-rows: 1

   * - Name
     - Description
   * - ``ConfigInterface``
     - Main interface for config objects. Supports dot notation, iteration, array access, and mutation. See `PHPDoc <https://github.com/php-fast-forward/config/blob/main/src/ConfigInterface.php>`_.
   * - ``ArrayConfig``
     - Stores config in memory using dot notation. Implements ``ConfigInterface``. See `ArrayConfig source <https://github.com/php-fast-forward/config/blob/main/src/ArrayConfig.php>`_.
   * - ``AggregateConfig``
     - Aggregates multiple config sources. Implements ``ConfigInterface``. See `AggregateConfig source <https://github.com/php-fast-forward/config/blob/main/src/AggregateConfig.php>`_.
   * - ``DirectoryConfig``
     - Loads config from a directory of PHP files. Implements ``ConfigInterface``. See `DirectoryConfig source <https://github.com/php-fast-forward/config/blob/main/src/DirectoryConfig.php>`_.
   * - ``CachedConfig``
     - Wraps config with PSR-16 cache. Implements ``ConfigInterface``. See `CachedConfig source <https://github.com/php-fast-forward/config/blob/main/src/CachedConfig.php>`_.

For traits and helpers, see also:
- `ArrayAccessConfigTrait <https://github.com/php-fast-forward/config/blob/main/src/ArrayAccessConfigTrait.php>`_
- `LazyLoadConfigTrait <https://github.com/php-fast-forward/config/blob/main/src/LazyLoadConfigTrait.php>`_
- `ConfigHelper <https://github.com/php-fast-forward/config/blob/main/src/Helper/ConfigHelper.php>`_
