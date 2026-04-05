Helpers And Traits
==================

These types are especially relevant when you are extending the package or integrating it into your own abstractions.

ConfigHelper
------------

``FastForward\\Config\\Helper\\ConfigHelper`` is a static utility class used internally and available for custom integrations.

.. list-table:: Main helper methods
   :header-rows: 1

   * - Method
     - Purpose
   * - ``isAssoc(mixed $value): bool``
     - Detects whether a value is an associative array.
   * - ``normalize(array $config): array``
     - Converts dot-notated associative keys into nested arrays.
   * - ``flatten(array $config, string $rootKey = ''): Traversable``
     - Flattens nested arrays into dot-notated key/value pairs.

``normalize()`` is what makes inputs such as ``['database.host' => 'localhost']`` behave like nested configuration. ``flatten()`` is what makes iteration yield dot-notated leaf keys.

ArrayAccessConfigTrait
----------------------

``ArrayAccessConfigTrait`` provides array-like syntax by delegating these operations:

- ``offsetExists()`` to ``has()``
- ``offsetGet()`` to ``get()``
- ``offsetSet()`` to ``set()``
- ``offsetUnset()`` to ``remove()``

This trait is the reason code like ``$config['app.name']`` works.

LazyLoadConfigTrait
-------------------

``LazyLoadConfigTrait`` is the building block behind all lazy config types in the package.

To use it in your own implementation, provide an ``__invoke(): ConfigInterface`` method. The trait will:

- resolve the real config object on first use;
- cache that resolved object internally;
- delegate ``get()``, ``has()``, ``set()``, ``remove()``, ``toArray()``, iteration, and array access to it.

Most application code does not need to interact with this trait directly, but it is an important extension point for custom config sources.
