Provider-Based Configuration
============================

Providers are a good fit when configuration belongs to modules, packages, or bounded contexts. Each provider is simply an invokable class that returns an array.

Minimal Provider Example
------------------------

.. code-block:: php

   final class BlogConfigProvider
   {
       public function __invoke(): array
       {
           return [
               'blog' => [
                   'posts_per_page' => 10,
               ],
           ];
       }
   }

Loading Providers Explicitly
----------------------------

.. code-block:: php

   use function FastForward\Config\configProvider;

   $config = configProvider([
       new BlogConfigProvider(),
   ]);

Mixing Providers With Other Sources
-----------------------------------

``config()`` can combine providers, arrays, and directories in a single call:

.. code-block:: php

   use function FastForward\Config\config;

   $config = config(
       ['app.env' => 'production'],
       __DIR__ . '/config',
       BlogConfigProvider::class,
   );

Provider Order
--------------

Provider order matters. Later providers override earlier values for the same keys while leaving unrelated nested values intact.

.. code-block:: php

   $config = configProvider([
       new CoreConfigProvider(),
       new FeatureFlagsProvider(),
       new LocalOverridesProvider(),
   ]);

Using A Cache File
------------------

To generate a PHP cache file, pass ``cachedConfigFile`` and include the explicit
Laminas enabling flag in provider output. The parent directory must exist and be
writable by the PHP process. With Composer's autoloader loaded, this example is
self-contained:

.. code-block:: php

   use Laminas\ConfigAggregator\ConfigAggregator;
   use function FastForward\Config\configProvider;

   $cacheDirectory = __DIR__ . '/var/cache';
   if (!is_dir($cacheDirectory)) {
       mkdir($cacheDirectory, 0775, true);
   }

   $config = configProvider(
       [
           static fn(): array => [
               ConfigAggregator::ENABLE_CACHE => true,
               'blog' => ['posts_per_page' => 10],
           ],
       ],
       cachedConfigFile: $cacheDirectory . '/providers.php',
   );

   $data = $config->toArray();

The flag is ``config_cache_enabled`` and is evaluated after provider merging, so
a later provider can disable generation with ``false``. An existing cache file is
read before providers run; changing a provider's flag does not bypass that file.
Pass ``cachedConfigFile: null`` to bypass file caching, or remove the file before
rebuilding it. See :doc:`caching` for directory examples and PSR-16 caching.

Good To Know
------------

- Providers are resolved lazily, not when you instantiate the config object.
- Returning arrays with dot-notated keys is fine; ``ArrayConfig`` will normalize them.
- ``config()`` automatically recognizes invokable provider class names, which makes simple examples shorter.
