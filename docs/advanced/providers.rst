Laminas-style Providers
=======================

You can use Laminas-compatible configuration providers with FastForward Config. Providers are PHP classes that implement an ``__invoke()`` method returning an array of configuration data. This makes it easy to integrate with existing Laminas ecosystem or modularize your config.

How it works
------------
- Each provider must be a class with an ``__invoke()`` method that returns an array.
- You can pass provider objects or class names to ``configProvider()`` or ``config()``.
- Providers are lazily invoked only when config is accessed.

Example
-------

.. code-block:: php

   class MyConfigProvider {
       public function __invoke() {
           return [
               'my' => [ 'setting' => true ]
           ];
       }
   }

   $config = configProvider([
       new MyConfigProvider(),
   ]);

Tips
----
- You can mix providers, arrays, and directories in a single config aggregation.
- Providers are great for dynamic or environment-based config.

See also:
- `Laminas Config Aggregator <https://docs.laminas.dev/laminas-config-aggregator/>`_
- `Live Coverage Report <../../public/coverage/index.html>`_
