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

If you want provider aggregation to write a cache file, pass ``cachedConfigFile``:

.. code-block:: php

   $config = configProvider(
       [
           new CoreConfigProvider(),
           new BlogConfigProvider(),
       ],
       cachedConfigFile: __DIR__ . '/../var/cache/providers.php',
   );

Good To Know
------------

- Providers are resolved lazily, not when you instantiate the config object.
- Returning arrays with dot-notated keys is fine; ``ArrayConfig`` will normalize them.
- ``config()`` automatically recognizes invokable provider class names, which makes simple examples shorter.
