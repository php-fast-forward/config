Common Use Cases
================

This page shows realistic combinations of the package features so you can copy a pattern instead of assembling everything from scratch.

Small Application Or Test Suite
-------------------------------

If your configuration already exists in memory, ``ArrayConfig`` or ``config()`` is enough:

.. code-block:: php

   use function FastForward\Config\config;

   $config = config([
       'app.name' => 'Console Tool',
       'app.env' => 'dev',
   ]);

   echo $config->get('app.name');

Layering Defaults And Local Overrides
-------------------------------------

Later sources override earlier ones, which makes environment-specific overrides easy to understand:

.. code-block:: php

   use function FastForward\Config\config;

   $config = config(
       ['database.host' => 'db.internal', 'database.port' => 3306],
       ['database.host' => '127.0.0.1'],
   );

   echo $config->get('database.host'); // 127.0.0.1
   echo $config->get('database.port'); // 3306

Module-Based Applications
-------------------------

Providers work well when each module or package ships its own configuration:

.. code-block:: php

   use function FastForward\Config\configProvider;

   $config = configProvider([
       new Core\ConfigProvider(),
       new Blog\ConfigProvider(),
       new Admin\ConfigProvider(),
   ]);

   print_r($config->toArray());

File-Based Projects
-------------------

If your project already uses a ``config/`` directory, keep that structure and load it directly:

.. code-block:: php

   use function FastForward\Config\configDir;

   $config = configDir(__DIR__ . '/config', recursive: true);

   echo $config->get('mail.transport');

Exposing Config Through PSR-11
------------------------------

Wrap the config object in ``ConfigContainer`` when another layer expects container-style access:

.. code-block:: php

   use FastForward\Config\Container\ConfigContainer;
   use function FastForward\Config\config;

   $config = config(__DIR__ . '/config');
   $container = new ConfigContainer($config);

   echo $container->get('config.database.host');

Production Cache
----------------

Use ``configCache()`` when you want a PSR-16 cache around the final merged config:

.. code-block:: php

   use Psr\SimpleCache\CacheInterface;
   use function FastForward\Config\configCache;

   /** @var CacheInterface $cache */
   $config = configCache($cache, __DIR__ . '/config');

For directory and provider aggregations, you can also use a dedicated cache file. See :doc:`../advanced/caching`.
