Lazy Loading
============

FastForward Config uses lazy loading to defer the loading and merging of configuration data until it is actually needed. This is achieved via the ``__invoke()`` method on config objects, which builds the final configuration only on first access.

How it works
------------

- When you create a config object (e.g., with ``config()`` or ``configDir()``), no files or providers are loaded immediately.
- The actual loading and merging only happens when you call a method like ``get()``, ``set()``, or use the object as an array.
- This is implemented via the ``LazyLoadConfigTrait`` (see `source <https://github.com/php-fast-forward/config/blob/main/src/LazyLoadConfigTrait.php>`_).

Benefits
--------
- Faster application startup.
- Only loads what you actually use.
- Supports dynamic and late-bound config sources.

Example
-------

.. code-block:: php

	$config = configDir(__DIR__ . '/config');
	// At this point, nothing is loaded yet!
	$env = $config->get('app.env'); // Now the config is loaded and merged.

See also:
- `Live Coverage Report <../../public/coverage/index.html>`_
