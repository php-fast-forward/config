Access & Mutation
=================

FastForward Config supports full access and mutation of configuration values, using dot notation for nested keys. This makes it easy to work with deeply structured config arrays.

**Set and get values:**

.. code-block:: php

   $config->set('db.host', 'localhost');
   echo $config->get('db.host'); // "localhost"

**Check if a key exists:**

.. code-block:: php

   $config->has('app.debug'); // true/false

**Export all configuration as array:**

.. code-block:: php

   print_r($config->toArray());

**Tips:**

- Dot notation works for both reading and writing.
- You can iterate over the config object (it implements IteratorAggregate).
- Config objects also support array access: ``$config['db.host']``

See also:
- `API Reference <../api.html>`_
- `Live Coverage Report <../../public/coverage/index.html>`_
