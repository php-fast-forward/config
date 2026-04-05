Integration
===========

This page focuses on the main integration hook exposed by the package: ``ConfigContainer``.

Using ConfigContainer
---------------------

``FastForward\\Config\\Container\\ConfigContainer`` adapts any ``ConfigInterface`` instance to ``Psr\Container\ContainerInterface``.

.. code-block:: php

   use FastForward\Config\ConfigInterface;
   use FastForward\Config\Container\ConfigContainer;
   use function FastForward\Config\config;

   $config = config([
       'database.host' => 'localhost',
       'database.port' => 3306,
   ]);

   $container = new ConfigContainer($config);

   $sameConfig = $container->get('config');
   $sameConfigAgain = $container->get(ConfigInterface::class);
   $host = $container->get('config.database.host');

Resolved Identifiers
--------------------

.. list-table:: Identifiers handled by ConfigContainer
   :header-rows: 1

   * - Identifier
     - Returns
   * - ``config``
     - The wrapped config object.
   * - ``FastForward\\Config\\ConfigInterface``
     - The wrapped config object.
   * - The concrete config class name
     - The wrapped config object.
   * - ``config.some.key``
     - The resolved configuration value.
   * - ``FastForward\\Config\\Container\\ConfigContainer``
     - The container wrapper itself.

When To Use It
--------------

``ConfigContainer`` is most useful when:

- a part of your application already expects ``ContainerInterface``;
- you want container-style lookup for config values such as ``config.database.host``;
- you want to expose the config object itself under a stable alias.

Troubleshooting
---------------

- If ``has('config.some.key')`` is false, confirm that the key exists in the wrapped config object.
- If ``get()`` cannot resolve an identifier, ``ContainerNotFoundException`` is thrown.
- If your application already has its own container, you can still use ``ConfigContainer`` as a small adapter or register the wrapped config object under similar service names there.
