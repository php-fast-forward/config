<?php

declare(strict_types=1);

use FastForward\Config\ConfigInterface;
use FastForward\Config\Container\ConfigContainer;
use FastForward\Config\DirectoryConfig;
use FastForward\Config\Exception\ContainerNotFoundException;

require __DIR__ . '/vendor/autoload.php';

if (function_exists('mb_rtrim') || function_exists('mb_substr') || function_exists('mb_strlen')) {
    throw new RuntimeException('Run this smoke test with multibyte functions disabled and runtime dependencies only.');
}

$directory = sys_get_temp_dir() . '/ff-config-runtime-' . bin2hex(random_bytes(8)) . '-配置';
mkdir($directory);
$file = $directory . '/app.php';

try {
    file_put_contents($file, '<?php return ["应用" => ["名称" => "配置"]];');

    $config    = new DirectoryConfig($directory . \DIRECTORY_SEPARATOR . \DIRECTORY_SEPARATOR);
    $container = new ConfigContainer($config);

    if (! $container->has(ConfigContainer::class) || $container !== $container->get(ConfigContainer::class)) {
        throw new RuntimeException('Container self binding must agree between has() and get().');
    }

    foreach (['config', ConfigInterface::class, DirectoryConfig::class] as $id) {
        if (! $container->has($id) || $config !== $container->get($id)) {
            throw new RuntimeException('Configuration binding did not resolve.');
        }
    }

    if (! $container->has('config.应用.名称') || '配置' !== $container->get('config.应用.名称')) {
        throw new RuntimeException('Unicode directory and configuration keys did not resolve.');
    }

    foreach (['configX应用.名称', 'config/应用.名称', 'config.', 'config././'] as $id) {
        if ($container->has($id)) {
            throw new RuntimeException('Invalid identifier unexpectedly resolved.');
        }

        try {
            $container->get($id);
        } catch (ContainerNotFoundException) {
            continue;
        }

        throw new RuntimeException('Unresolved identifier must throw a container not-found exception.');
    }

    echo 'Runtime-only directory and container smoke test passed on PHP ' . \PHP_VERSION . \PHP_EOL;
} finally {
    @unlink($file);
    @rmdir($directory);
}
