Quick Start
===========

This page gives you a complete first example and then points you to the sections that explain each piece in more detail.

A First Working Example
-----------------------

.. code-block:: php

   <?php

   declare(strict_types=1);

   use function FastForward\Config\config;

   final class AppConfigProvider
   {
       public function __invoke(): array
       {
           return [
               'app.locale' => 'en_US',
               'features' => [
                   'search' => true,
               ],
           ];
       }
   }

   $config = config(
       ['app.name' => 'Example App', 'app.env' => 'production'],
       __DIR__ . '/config',
       AppConfigProvider::class,
   );

   echo $config->get('app.name'); // Example App
   echo $config->get('app.locale'); // en_US
   echo $config->get('database.host', '127.0.0.1'); // fallback when the key is missing

.. tip::

   ``config()`` is the best starting point for most users. It accepts arrays, existing ``ConfigInterface`` objects, readable directory paths, and invokable provider class names.

Loading Only From A Directory
-----------------------------

If all your configuration already lives in PHP files, use ``configDir()`` directly:

.. code-block:: php

   use function FastForward\Config\configDir;

   $config = configDir(__DIR__ . '/config', recursive: true);

   echo $config->get('app.name', 'Unnamed application');

Using Providers Explicitly
--------------------------

If you prefer module-style provider classes, ``configProvider()`` makes that intent explicit:

.. code-block:: php

   use function FastForward\Config\configProvider;

   $config = configProvider([
       new AppConfigProvider(),
   ]);

Next Steps
----------

- See :doc:`../usage/getting-services` to decide which entry point to use in your own project.
- See :doc:`../usage/access-mutation` to learn how reads, writes, defaults, iteration, and array access work.
- See :doc:`../advanced/caching` when you are ready to add cache layers.
