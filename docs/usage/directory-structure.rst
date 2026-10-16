Working With Directories
========================

Directory loading is a good fit when your project already stores configuration in separate PHP files. Each file must return an array.

Example Layout
--------------

.. code-block:: text

   config/
   |- app.php
   |- database.php
   `- local/
      `- overrides.php

Example file contents:

.. code-block:: php

   // config/app.php
   return [
       'app' => [
           'name' => 'Example App',
       ],
   ];

.. code-block:: php

   // config/database.php
   return [
       'database.host' => 'localhost',
       'database.port' => 3306,
   ];

Loading Top-Level Files Only
----------------------------

.. code-block:: php

   use function FastForward\Config\configDir;

   $config = configDir(__DIR__ . '/config');

This reads files directly inside ``config/`` and ignores subdirectories.

Loading Recursively
-------------------

.. code-block:: php

   use function FastForward\Config\config;
   use function FastForward\Config\configDir;

   $explicit = configDir(__DIR__ . '/config', recursive: true);
   $implicit = config(__DIR__ . '/config');

Both examples load nested PHP files. The second form is useful when you want ``config()`` to combine directories with arrays or providers in a single call.

Adding A Cache File
-------------------

Both ``configDir()`` and ``DirectoryConfig`` support an optional cache file path
through Laminas ConfigAggregator. Cache generation also requires a truthy
``ConfigAggregator::ENABLE_CACHE`` (``config_cache_enabled``) value in the merged
configuration. Add this file to the directory being loaded:

.. code-block:: php

   <?php
   // config/cache.php
   use Laminas\ConfigAggregator\ConfigAggregator;

   return [ConfigAggregator::ENABLE_CACHE => true];

Create the cache file's parent directory and make it writable by the PHP process.
Store generated cache files outside ``config/`` so they are not scanned as inputs:

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

An existing cache file is read before configuration files are loaded. Disabling
``ENABLE_CACHE`` prevents generating a missing cache file; it does not ignore an
existing one. Pass ``cachedConfigFile: null`` to bypass file caching, or remove the
old cache file before rebuilding it. See :doc:`../advanced/caching`.

Tips
----

- Keep each file focused on one topic such as ``app.php``, ``database.php``, or ``queue.php``.
- Use recursive loading when you want subdirectories like ``local/`` or ``modules/``.
- Combine directories with arrays or providers when you need local overrides or package defaults.
