FAQ
===

**Q: Can I use dot notation for nested keys?**
A: Yes! Dot notation is fully supported for both getting and setting values, e.g. ``$config->get('foo.bar.baz')``.

**Q: How do I cache my configuration?**
A: Use the ``configCache()`` function with any PSR-16 cache implementation. See :doc:`advanced/caching`.

**Q: Can I load config from multiple sources?**
A: Yes, aggregate as many arrays, directories, or providers as you want. See :doc:`usage/index`.

**Q: Is this compatible with Laminas ConfigAggregator?**
A: Yes, you can use Laminas-style providers and directory structures. See :doc:`advanced/providers`.

**Q: Where can I see test coverage and examples?**
A: See the `Live Coverage Report <public/coverage/index.html>`_ and the `tests/` directory in the repository.

**Q: How do I contribute or report issues?**
A: Open an issue or PR on the `GitHub repository <https://github.com/php-fast-forward/config>`_.
