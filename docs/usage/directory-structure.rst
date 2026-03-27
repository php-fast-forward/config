Directory Structure Example
==========================

FastForward Config can load and aggregate all PHP files in a directory tree. This is especially useful for large projects with modular configuration.

**Typical structure:**

.. code-block:: text

    config/
    ├── app.php
    ├── db.php
    └── services/
         └── mail.php

You can load this entire structure with:

.. code-block:: php

    $config = configDir(__DIR__ . '/config', recursive: true);

**Tips:**
- Each PHP file should return an array.
- Subdirectories are supported when ``recursive: true``.
- You can combine directory configs with arrays and providers.

See also:
- :doc:`../advanced/providers`
- `Live Coverage Report <../../public/coverage/index.html>`_
