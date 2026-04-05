Creating Config Objects
=======================

Beginners should normally start with ``config()``. The lower-level classes are still available when you need stricter control over loading, caching, or integration.

.. list-table:: Choose the right entry point
   :header-rows: 1

   * - Entry point
     - Best for
     - Notes
   * - ``config()``
     - Most applications.
     - Accepts arrays, existing ``ConfigInterface`` objects, readable directories, and invokable provider class names.
   * - ``configDir()``
     - Loading PHP files from one directory.
     - Non-recursive by default. Pass ``recursive: true`` to include subdirectories.
   * - ``configProvider()``
     - Modular applications using provider classes.
     - Delegates merging and optional cache files to Laminas ConfigAggregator.
   * - ``new ArrayConfig()``
     - Tests or small in-memory setups.
     - Gives you an eager config object immediately.
   * - ``new ConfigContainer()``
     - PSR-11 integration.
     - Exposes config values through ``config`` and ``config.*`` identifiers.

Using The Default Factory
-------------------------

``config()`` is the most flexible starting point because it can combine multiple source types in one call:

.. code-block:: php

   use function FastForward\Config\config;

   $config = config(
       ['app.env' => 'production'],
       __DIR__ . '/config',
       Vendor\Package\ConfigProvider::class,
   );

   echo $config->get('app.env');

.. tip::

   When you pass a readable directory string to ``config()``, the package treats it as recursive directory loading. That is different from ``configDir()``, which is non-recursive by default.

Using Directories Explicitly
----------------------------

Use ``configDir()`` when directory loading is the only behavior you need and you want to control recursion explicitly:

.. code-block:: php

   use function FastForward\Config\configDir;

   $config = configDir(__DIR__ . '/config', recursive: true);

Using Providers Explicitly
--------------------------

Use ``configProvider()`` when your application is organized around module or package providers:

.. code-block:: php

   use function FastForward\Config\configProvider;

   $config = configProvider([
       new App\ConfigProvider(),
       new Billing\ConfigProvider(),
   ]);

Using Concrete Classes Directly
-------------------------------

Direct instantiation is useful when you want more control or clearer intent in framework code:

- ``ArrayConfig`` for tests, fixtures, and already-assembled arrays.
- ``DirectoryConfig`` and ``RecursiveDirectoryConfig`` when you want explicit directory loader types.
- ``CachedConfig`` when you need a custom cache key or write-through cache updates with ``persistent: true``.
- ``ConfigContainer`` when another part of your system expects a PSR-11 container.

See Also
--------

- :doc:`access-mutation`
- :doc:`use-cases`
- :doc:`../advanced/integration`
