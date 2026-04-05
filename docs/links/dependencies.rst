Dependencies
============

This page explains why each declared dependency exists, so new users can understand what the package relies on and what they still need to bring from their own application stack.

Runtime Dependencies
--------------------

.. list-table:: Composer runtime requirements
   :header-rows: 1

   * - Package
     - Why it is used
   * - ``php``
     - The package requires PHP ``8.3`` or newer.
   * - ``dflydev/dot-access-data``
     - Provides the dot-notation storage primitives used by ``ArrayConfig``.
   * - ``laminas/laminas-config-aggregator``
     - Provides provider aggregation, PHP file providers, and optional cache-file support.
   * - ``psr/container``
     - Defines the PSR-11 contracts implemented by ``ConfigContainer``.
   * - ``psr/simple-cache``
     - Defines the PSR-16 cache contract used by ``CachedConfig`` and ``configCache()``.

Development Dependencies
------------------------

.. list-table:: Composer development requirements
   :header-rows: 1

   * - Package
     - Why it is used
   * - ``fast-forward/dev-tools``
     - Supplies the shared development tooling used by the Fast Forward ecosystem.

Integration Notes
-----------------

- The package ships with the PSR-16 interface, but not with a concrete cache backend. Bring your own cache implementation when you use ``configCache()``.
- Provider-based and directory-based caching rely on Laminas ConfigAggregator and a writable cache-file path.
