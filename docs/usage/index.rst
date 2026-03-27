Usage
=====

This section covers all main features of FastForward Config, with practical examples and detailed explanations.

**Key Features:**

- **Dot notation access:** Retrieve nested values easily: ``$config->get('app.env')``
- **Load from arrays, directories, or providers:** Aggregate multiple sources seamlessly.
- **Lazy-loading:** Config is only loaded and merged when first accessed, improving performance.
- **Aggregation:** Merge as many config sources as you want, including arrays, directories, and provider classes.
- **Recursive directory support:** Load all PHP config files from a directory tree.
- **PSR-16 caching:** Optionally cache your config for fast repeated access.
- **Laminas ConfigProvider compatibility:** Use Laminas-style providers out of the box.

For each feature, see the examples below and in the :doc:`../getting-started/quickstart`.

.. toctree::
   :maxdepth: 2

   access-mutation
   directory-structure
