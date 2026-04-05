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

Both ``configDir()`` and ``DirectoryConfig`` support an optional cache file path through Laminas ConfigAggregator:

.. code-block:: php

   $config = configDir(
       __DIR__ . '/config',
       recursive: true,
       cachedConfigFile: __DIR__ . '/../var/cache/config.php',
   );

Tips
----

- Keep each file focused on one topic such as ``app.php``, ``database.php``, or ``queue.php``.
- Use recursive loading when you want subdirectories like ``local/`` or ``modules/``.
- Combine directories with arrays or providers when you need local overrides or package defaults.
