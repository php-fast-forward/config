# PSR-11 and FastForward Container

Load when callers expect PSR-11 or factories consume configuration. Run `examples/containers.php`. The standalone adapter always runs; optional full-container integration requires `fast-forward/container` with a supported `^1.9` API.

## Raw paths and exposed identifiers

```php
$config = new FastForward\Config\ArrayConfig(['app' => ['name' => 'Store']]);
$container = new FastForward\Config\Container\ConfigContainer($config);

$config->get('app.name');                // 'Store'
$container->get('config.app.name');      // 'Store'
$container->get('config') === $config;   // true
$container->get(FastForward\Config\ConfigInterface::class) === $config; // true
```

The concrete config class resolves the wrapped object. The adapter's own class
resolves itself, and `has()` reports both internal bindings consistently.

Raw keys do not start with `config.`. The prefix must be exact and its suffix
nonempty: `config.`, `configuration.app.name` and unknown leaf identifiers
return false from `has()` and raise `ContainerNotFoundException` from `get()`.
The prefix is a naming convention, not authorization. No mbstring extension
is needed.

## Optional full-container composition

Inspect that library's installed version too. In the supported ^1.9 API, nest initializers under its own interface name:

```php
use FastForward\Config\ArrayConfig;
use FastForward\Container\ContainerInterface as ContainerConfig;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Psr\Container\ContainerInterface;
use function FastForward\Container\container;

$config = new ArrayConfig([
    'app' => ['name' => 'Store'],
    ContainerConfig::class => [
        new ArrayServiceProvider([
            'app.label' => static fn(ContainerInterface $c): string =>
                'Hello ' . $c->get('config.app.name'),
        ]),
    ],
]);
$services = container($config);
if ($services->get('app.label') !== 'Hello Store') {
    throw new RuntimeException('Configuration-backed factory failed');
}
```

Use `FastForward\Container\ContainerInterface::class`, not the PSR interface, for that raw provider-list key. Resolve config in the composition root/factory; application services receive concrete values/collaborators.

Config lets later scalar sources win; FastForward Container tries earlier sources first. Pass test overrides first, e.g. `container($testOverrides, $config)`, and resolve representative services. Do not assume internal resolution or cached services refresh when source config changes.
