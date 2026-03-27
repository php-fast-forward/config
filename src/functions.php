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

namespace FastForward\Config;

use Psr\SimpleCache\CacheInterface;

/**
 * Creates a unified configuration object from various sources.
 *
 * This function SHALL normalize and aggregate mixed input types such as arrays,
 * directories, and Laminas configuration providers into a single configuration instance.
 *
 * @param array|ConfigInterface|string ...$configs The configuration sources.
 *
 * @return ConfigInterface the aggregated configuration instance
 */
function config(array|ConfigInterface|string ...$configs): ConfigInterface
{
    foreach ($configs as $index => $config) {
        if (\is_array($config)) {
            $configs[$index] = new ArrayConfig($config);
        }

        if (\is_string($config) && is_dir($config) && is_readable($config)) {
            $configs[$index] = configDir($config, true);
        }

        if (\is_string($config)
            && class_exists($config)
            && method_exists($config, '__invoke')
        ) {
            $configs[$index] = configProvider([$config]);
        }
    }

    return new AggregateConfig(...$configs);
}

/**
 * Creates a cached configuration object from various sources.
 *
 * This function SHALL wrap the configuration in a caching layer using PSR-16 CacheInterface.
 *
 * @param CacheInterface $cache the cache pool for storing configuration data
 * @param array|ConfigInterface|string ...$configs The configuration sources.
 *
 * @return ConfigInterface the cached configuration instance
 */
function configCache(CacheInterface $cache, array|ConfigInterface|string ...$configs): ConfigInterface
{
    return new CachedConfig(cache: $cache, defaultConfig: config(...$configs));
}

/**
 * Creates a directory-based configuration provider.
 *
 * If the recursive flag is TRUE, nested directories SHALL be included in the scan.
 * Configuration files MUST follow the PHP file format.
 *
 * @param string $rootDirectory the directory to load configuration files from
 * @param bool $recursive whether to include files in subdirectories recursively
 * @param string|null $cachedConfigFile optional path to a cache file for the configuration
 *
 * @return ConfigInterface the resulting configuration provider instance
 */
function configDir(
    string $rootDirectory,
    bool $recursive = false,
    ?string $cachedConfigFile = null,
): ConfigInterface {
    $configClass = $recursive
        ? RecursiveDirectoryConfig::class
        : DirectoryConfig::class;

    return new $configClass($rootDirectory, $cachedConfigFile);
}

/**
 * Creates a configuration from a list of Laminas-style configuration providers.
 *
 * Each provider MUST be invokable and return an array or configuration structure.
 *
 * @param iterable $providers a list of configuration providers
 * @param string|null $cachedConfigFile optional path to a cache file for the configuration
 *
 * @return ConfigInterface the resulting configuration instance
 */
function configProvider(iterable $providers, ?string $cachedConfigFile = null): ConfigInterface
{
    return new LamiasConfigAggregatorConfig(providers: $providers, cachedConfigFile: $cachedConfigFile);
}
