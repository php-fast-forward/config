Installation
============

Requirements
------------

.. list-table:: Minimum requirements
   :header-rows: 1

   * - Requirement
     - Notes
   * - PHP
     - Version ``8.3`` or newer.
   * - Composer
     - Used to install the package and its runtime dependencies.
   * - PHP config files
     - Required only when you load configuration from directories. Each file must return an array.

Install The Package
-------------------

.. code-block:: bash

   composer require fast-forward/config

What Gets Installed
-------------------

- ``dflydev/dot-access-data`` powers dot-notation reads and writes.
- ``laminas/laminas-config-aggregator`` powers provider aggregation and optional cache files.
- ``psr/container`` enables PSR-11 integration through ``ConfigContainer``.
- ``psr/simple-cache`` enables ``configCache()`` and ``CachedConfig``.

.. note::

   FastForward Config depends on the PSR-16 cache interface, not on a concrete cache backend. If you want to use ``configCache()`` in a real application, provide any PSR-16 compatible implementation from your stack.

Importing The Helper Functions
------------------------------

The factory helpers are plain PHP functions in the ``FastForward\\Config`` namespace. Import them with ``use function``:

.. code-block:: php

   use function FastForward\Config\config;
   use function FastForward\Config\configCache;
   use function FastForward\Config\configDir;
   use function FastForward\Config\configProvider;

This detail is easy to miss if you are used to importing classes only.
