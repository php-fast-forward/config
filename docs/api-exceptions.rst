Exceptions
==========

The package uses focused exceptions so callers can react to invalid input and container lookup failures clearly.

.. list-table:: Public exceptions
   :header-rows: 1

   * - Exception
     - Thrown when
   * - ``FastForward\\Config\\Exception\\InvalidArgumentException``
     - A method receives invalid input such as an unreadable directory or an invalid ``set()`` key/value shape.
   * - ``FastForward\\Config\\Exception\\ContainerNotFoundException``
     - ``ConfigContainer`` cannot resolve the requested identifier.

InvalidArgumentException
------------------------

Typical scenarios include:

- creating ``DirectoryConfig`` or ``RecursiveDirectoryConfig`` with a directory that does not exist or is not readable;
- calling ``set()`` with a non-string key while also passing a standalone value.

ContainerNotFoundException
--------------------------

This exception implements ``Psr\\Container\\NotFoundExceptionInterface`` and is raised by ``ConfigContainer`` when:

- the identifier is unknown;
- the identifier does not start with ``config`` and is not one of the built-in aliases;
- ``config.some.key`` points to a missing config entry.

Example
-------

.. code-block:: php

   use FastForward\Config\Container\ConfigContainer;
   use FastForward\Config\Exception\ContainerNotFoundException;
   use function FastForward\Config\config;

   $container = new ConfigContainer(config(['app.name' => 'Example']));

   try {
       $container->get('config.missing.value');
   } catch (ContainerNotFoundException $exception) {
       echo $exception->getMessage();
   }
