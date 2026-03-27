Quick Start
===========

This section shows how to use FastForward Config in real-world scenarios. For more advanced usage, see the :doc:`../usage/index` and :doc:`../advanced/index` sections.

Load configuration from multiple source
---------------------------------------

You can aggregate arrays, directories, and provider classes:

.. code-block:: php

   use FastForward\Config\{config, configDir, configCache};
   use Symfony\Component\Cache\Simple\FilesystemCache;

   $config = config(
       ['app' => ['env' => 'production']],
       __DIR__ . '/config',
       \Vendor\Package\ConfigProvider::class
   );

   echo $config->get('app.env'); // "production"

Cache configuration using PSR-16
--------------------------------

Wrap your config with a PSR-16 cache for performance:

.. code-block:: php

   $cache = new FilesystemCache();

   $config = configCache(
       cache: $cache,
       ['foo' => 'bar']
   );

   echo $config->get('foo'); // "bar"

Load from a recursive directory
-------------------------------

Aggregate all PHP files in a directory (including subfolders):

.. code-block:: php

   $config = configDir(__DIR__ . '/config', recursive: true);

Use Laminas-style providers
---------------------------

Providers are classes with an ``__invoke()`` method returning an array:

.. code-block:: php

   $config = configProvider([
       new Vendor\Package\Provider1(),
       new Vendor\Package\Provider2(),
   ]);

See also:

- `Live Coverage Report <../public/coverage/index.html>`_
- :doc:`../api`
