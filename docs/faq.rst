FAQ
===

Where should I start if I am completely new to the package?
   Start with :doc:`getting-started/installation` and :doc:`getting-started/quickstart`. Those pages explain the import style, the simplest factory function, and the first code you can copy safely.

When should I use ``config()`` instead of ``configDir()`` or ``configProvider()``?
   Use ``config()`` as the default choice. It is the most beginner-friendly entry point because it accepts arrays, readable directories, existing config objects, and invokable provider class names in one place. Use ``configDir()`` or ``configProvider()`` when you want to express that one specific loading strategy explicitly.

Does ``config()`` load directory strings recursively?
   Yes. If you pass a readable directory string to ``config()``, it is treated as recursive directory loading. This is convenient, but it is different from ``configDir()``, which is non-recursive unless you pass ``recursive: true``.

How do I read nested values safely?
   Use ``get()`` with a default value, for example ``$config->get('database.host', '127.0.0.1')``. Use ``has()`` when you need to know whether a key exists before reading it.

Why does ``get('database')`` sometimes return an object instead of an array?
   Associative subtrees are returned as ``ArrayConfig`` so you can keep using ``get()``, ``has()``, and ``toArray()`` on the nested branch. Sequential lists remain normal PHP arrays.

Can I change values after the config object is created?
   Yes. All ``ConfigInterface`` implementations support ``set()``, ``remove()``, and array access. See :doc:`usage/access-mutation`.

What happens when two sources define the same key?
   Later sources override earlier values for the same key. Nested associative data is merged so that unrelated keys are preserved.

How do I use the package with a PSR-11 container?
   Wrap the config object in ``FastForward\\Config\\Container\\ConfigContainer``. Then use identifiers such as ``config`` for the whole config or ``config.database.host`` for one value. See :doc:`advanced/integration`.

What is the difference between ``configCache()`` and ``cachedConfigFile``?
   ``configCache()`` uses a PSR-16 backend and caches the final merged array. ``cachedConfigFile`` delegates to Laminas ConfigAggregator and stores a generated PHP cache file on disk. See :doc:`advanced/caching`.

What errors should I expect?
   ``InvalidArgumentException`` is used for invalid inputs such as unreadable directories. ``ContainerNotFoundException`` is used when ``ConfigContainer`` cannot resolve an identifier. See :doc:`api-exceptions`.

Can I load JSON, YAML, or XML files directly?
   Not with a built-in loader. The package is intentionally focused on arrays, PHP files, and provider classes. If you need another format, convert it to an array or wrap it in a provider.

Where can I inspect tested scenarios and coverage?
   See :doc:`links/coverage`. The test suite covers arrays, aggregation, directory loading, caching, traits, helper behavior, and PSR-11 container access.
