Accessing And Mutating Values
=============================

All ``ConfigInterface`` implementations support the same read, write, remove, iterate, and export operations. The examples below use dot notation because that is the most convenient style for nested configuration.

Path traversal and storage use native PHP arrays. Both ``.`` and ``/`` separate path segments, so ``database.host`` and ``database/host`` address the same value. Leading, trailing, and repeated separators retain empty segments.

Reading Values
--------------

.. code-block:: php

   echo $config->get('database.host', '127.0.0.1');

   if ($config->has('app.env')) {
       echo $config->get('app.env');
   }

When a key points to an associative subtree, ``get()`` returns another ``ArrayConfig`` instance. This is helpful when you want to keep working with the subtree as configuration instead of converting it immediately.

.. code-block:: php

   $database = $config->get('database');

   echo $database->get('host');
   print_r($database->toArray());

Sequential lists remain plain PHP arrays:

.. code-block:: php

   $hosts = $config->get('cluster.hosts', []);

   foreach ($hosts as $host) {
       echo $host . PHP_EOL;
   }

Writing And Merging Values
--------------------------

You can set one key at a time, merge an array, or merge another config object.

.. code-block:: php

   $config->set('database.host', 'db.internal');

   $config->set([
       'database.port' => 3306,
       'app.locale' => 'en_US',
   ]);

   $config->set($otherConfig);

Later writes override earlier values for the same key while preserving unrelated nested values.
For arrays, ``set()`` replaces matching indexes and retains unmatched indexes; it does not
concatenate lists. Remove the key before setting it when you need whole-list replacement.
Source aggregation has separate :doc:`merge semantics <use-cases>`.

Removing Values
---------------

.. code-block:: php

   $config->remove('database.password');

   if (! $config->has('database.password')) {
       echo 'Password removed';
   }

Exporting And Iterating
-----------------------

``toArray()`` exports the whole nested structure:

.. code-block:: php

   print_r($config->toArray());

Iteration is flattened into dot-notated leaf keys, which is convenient for debugging and inspection:

.. code-block:: php

   foreach ($config as $key => $value) {
       echo $key . '=' . json_encode($value) . PHP_EOL;
   }

Array Access
------------

Config objects also implement ``ArrayAccess``:

.. code-block:: php

   $config['app.name'] = 'Renamed App';

   echo $config['app.name'];
   unset($config['app.name']);

Good To Know
------------

- ``get()`` with a missing key returns your default value instead of failing.
- ``has()`` returns true for a stored ``null``, while ``get()`` returns the supplied default for that value.
- Empty arrays and arrays containing only string keys are returned as ``ArrayConfig`` objects. Sequential, sparse numeric, and mixed-key arrays remain plain arrays.
- An empty lookup path raises ``FastForward\Config\Exception\InvalidArgumentException``. Removing a missing path leaves the configuration unchanged, and removing a leaf retains its parent arrays.
- ``get('some.branch')`` may return another config object, not always a plain array.
- ``toArray()`` is the right choice when another library expects a regular PHP array.
