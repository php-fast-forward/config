Caching
=======

FastForward Config supports PSR-16 caching for configuration data. This allows you to cache the merged configuration, improving performance for large or complex setups.

How it works
------------
- Wrap your config with ``configCache($cache, ...)``.
- The first access will store the merged config in the cache.
- Subsequent accesses will read from cache, unless you clear it.
- Any PSR-16 compatible cache (e.g., Symfony, Doctrine, etc) is supported.

Example
-------

.. code-block:: php

   use Symfony\Component\Cache\Simple\FilesystemCache;

   $cache = new FilesystemCache();
   $config = configCache($cache, ['foo' => 'bar']);

Tips
----
- Use caching in production for best performance.
- You can combine caching with any config aggregation.

See also:
- `PSR-16 Simple Cache <https://www.php-fig.org/psr/psr-16/>`_
- `Live Coverage Report <../../public/coverage/index.html>`_
