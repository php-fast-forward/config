<?php

declare(strict_types=1);

/**
 * This file is part of php-fast-forward/config.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @copyright Copyright (c) 2025-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * @see       https://github.com/php-fast-forward/config
 * @see       https://github.com/php-fast-forward
 * @see       https://datatracker.ietf.org/doc/html/rfc2119
 */

namespace FastForward\Config\SkillExamples;

use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use FastForward\Config\Container\ConfigContainer;
use FastForward\Container\ContainerInterface as ContainerConfig;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$config = new ArrayConfig([
    'app' => [
        'name' => 'Store',
    ],
]);
$adapter = new ConfigContainer($config);
same('Store', $config->get('app.name'), 'Raw configuration lookup failed.');
same('Store', $adapter->get('config.app.name'), 'Prefixed PSR-11 lookup failed.');
same($config, $adapter->get('config'), 'The config alias must return the wrapped instance.');
same($config, $adapter->get(ConfigInterface::class), 'The interface alias must return the wrapped instance.');
same(true, $adapter->has(ConfigContainer::class), 'The adapter must expose its own class.');
same($adapter, $adapter->get(ConfigContainer::class), 'The own-class binding must resolve the adapter.');
foreach (['config.', 'configuration.app.name'] as $invalidId) {
    same(false, $adapter->has($invalidId), 'An invalid prefix or empty path must not exist.');
    $invalidCaught = false;
    try {
        $adapter->get($invalidId);
    } catch (NotFoundExceptionInterface) {
        $invalidCaught = true;
    }
    same(true, $invalidCaught, 'Invalid identifiers must use the PSR-11 not-found contract.');
}
same(false, $adapter->has('config.app.absent'), 'An absent leaf must not exist.');
$caught = false;
try {
    $adapter->get('config.app.absent');
} catch (NotFoundExceptionInterface) {
    $caught = true;
}

same(true, $caught, 'An absent leaf must use the PSR-11 not-found contract.');

if (! class_exists(ArrayServiceProvider::class)) {
    echo 'containers: ok; optional fast-forward/container integration skipped (not installed)', \PHP_EOL;
    exit(0);
}

$composedConfig = new ArrayConfig([
    'app' => [
        'name' => 'Store',
    ],
    ContainerConfig::class => [
        new ArrayServiceProvider([
            'app.label' => static fn(ContainerInterface $c): string => 'Hello ' . $c->get('config.app.name'),
        ]),
    ],
]);
$services = container($composedConfig);
same('Hello Store', $services->get('app.label'), 'A nested configured factory must receive the composed container.');
same('Store', $services->get('config.app.name'), 'The composed container must expose prefixed config leaves.');

$overrides = new ArrayServiceProvider([
    'app.label' => static fn(): string => 'Test',
]);
$overridden = container($overrides, $composedConfig);
same('Test', $overridden->get('app.label'), 'Earlier application service overrides must win.');

echo 'containers: ok; optional fast-forward/container integration: ok', \PHP_EOL;
