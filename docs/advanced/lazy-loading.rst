Lazy Loading
============

Most config objects in this package delay work until you actually use them. This keeps bootstrap code light and lets you compose multiple sources before paying the cost of loading and merging them.

Which Types Are Lazy
--------------------

The following types resolve themselves on first use:

- ``AggregateConfig``
- ``DirectoryConfig``
- ``RecursiveDirectoryConfig``
- ``LamiasConfigAggregatorConfig``
- ``CachedConfig``

``ArrayConfig`` is the main eager implementation because it already has all data in memory.

What Triggers Resolution
------------------------

The first call to any of the operations below will build the underlying config object:

- ``get()``
- ``has()``
- ``set()``
- ``remove()``
- ``toArray()``
- iteration with ``foreach``
- array access such as ``$config['app.env']``

After the first resolution, the trait reuses the same internal config instance.

Example
-------

.. code-block:: php

   use function FastForward\Config\configDir;

   $config = configDir(__DIR__ . '/config', recursive: true);
   // No files have been merged yet.

   $environment = $config->get('app.env');
   // The directory is read and merged here, on first access.

Why This Matters
----------------

- Startup stays simple even when you aggregate several sources.
- Errors from unreadable directories or broken providers may appear on first use instead of object creation.
- You can force resolution early by calling ``toArray()`` during bootstrap if that better matches your application's error handling.

For Library Authors
-------------------

The lazy behavior is implemented through ``LazyLoadConfigTrait``. This is mainly useful if you build your own ``ConfigInterface`` implementation and want it to defer work until first access.
