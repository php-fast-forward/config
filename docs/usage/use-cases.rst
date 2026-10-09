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

Later scalar values override earlier ones, while maps merge recursively and sequential lists concatenate in source order:

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

You can also compose module directories directly, followed by application configuration:

.. code-block:: php

   use function FastForward\Config\config;

   $config = config(
       '/app/modules/Apt/config',
       '/app/modules/Homebrew/config',
       '/app/modules/Casino/config',
       '/app/config',
   );

Lists such as ``console.commands`` and ``service_providers`` retain every entry,
including duplicates, in source order. For example:

.. code-block:: php

   $config = config(
       ['console.commands' => ['A', 'B']],
       ['console' => ['commands' => ['C', 'A']]],
   );

   print_r($config->get('console.commands')); // ['A', 'B', 'C', 'A']

This principal list case agrees with ``configProvider()``. Configuration objects,
PHP files, directories, and invokable provider class names can be mixed as sources;
lazy loading and dot-notation access are preserved.

Aggregation Boundary Semantics
------------------------------

``config()`` / ``AggregateConfig`` use the existing dot-access dependency's ``MERGE``
mode, scoped to source aggregation:

- Two sequential arrays (keys ``0`` through ``n - 1``) concatenate without deduplication.
- Empty arrays do not clear existing arrays. A later empty array replaces an earlier scalar.
- Sparse numeric arrays and mixed string/numeric arrays merge recursively by key,
  replacing matching indexes and preserving unrelated keys; they are not reindexed or concatenated as lists.
- A map/list conflict also merges by key, preserving both string and unrelated numeric keys.
- For array/scalar conflicts, the later value wins. ``null`` also overwrites an earlier
  value and remains present in ``toArray()`` / ``has()``; ``get()`` retains its existing default-value behavior for ``null``.

These boundary rules preserve the existing aggregation contract outside sequential-list
concatenation. Laminas-backed ``configProvider()`` and directory loaders retain their own
existing merge rules inside each source, including handling of non-sequential numeric keys.

Explicit mutation remains a separate operation: ``ArrayConfig::set()`` continues
to replace matching indexes and retain unmatched indexes. It does not concatenate
lists or guarantee whole-list replacement:

.. code-block:: php

   $config = new \FastForward\Config\ArrayConfig(['commands' => ['A', 'B']]);
   $config->set('commands', ['C']);
   print_r($config->get('commands')); // ['C', 'B']

   // Remove a list before assigning when whole-list replacement is required.
   $config->remove('commands');
   $config->set('commands', ['D']);
   print_r($config->get('commands')); // ['D']

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
