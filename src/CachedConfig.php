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
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

/**
 * Class CachedConfig.
 *
 * Provides a cached implementation of a configuration source.
 * This class SHALL attempt to cache the configuration output of the decorated ConfigInterface instance.
 * It SHALL lazily initialize and retrieve cached configuration data upon invocation.
 */
final class CachedConfig implements ConfigInterface
{
    use LazyLoadConfigTrait;

    private readonly string $cacheKey;

    private readonly ?int $cacheTtl;

    /**
     * Constructs a CachedConfig wrapper.
     *
     * This constructor SHALL accept a PSR-16 cache implementation and a configuration instance
     * to be cached. It MUST defer reading and writing the configuration until invoked.
     *
     * @param CacheInterface $cache the cache implementation used for storing configuration data
     * @param ConfigInterface $defaultConfig the configuration source to be cached
     * @param bool $persistent whether the cache should be persistent or not
     * @param string|null $cacheKey a shared cache key, or null for an isolated key whose entries expire after 300 seconds
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ConfigInterface $defaultConfig,
        private readonly bool $persistent = false,
        ?string $cacheKey = null,
    ) {
        $this->cacheKey = $cacheKey ?? 'ff_config_' . bin2hex(random_bytes(16));
        $this->cacheTtl = null === $cacheKey ? 300 : null;
    }

    /**
     * Invokes the configuration and returns the cached configuration data.
     *
     * A usable cache entry MUST be returned without resolving the source. A miss or unusable entry
     * SHALL resolve the source and attempt to cache it. A failed initial write MUST NOT discard source data.
     * This method MUST return a ConfigInterface implementation containing the cached configuration data.
     *
     * @return ConfigInterface a ConfigInterface implementation containing the cached configuration data
     *
     * @throws InvalidArgumentException if the cache key is invalid
     */
    public function __invoke(): ConfigInterface
    {
        $data = $this->cache->get($this->cacheKey);
        if (! \is_array($data)) {
            $data = $this->defaultConfig->toArray();
            $this->cache->set($this->cacheKey, $data, $this->cacheTtl);
        }

        return new ArrayConfig($data);
    }

    /**
     * Sets configuration data.
     *
     * This method MUST update the cached configuration data in the cache if the persistent flag is set to true.
     *
     * @param array|ConfigInterface|string $key the configuration key or an array of key-value pairs to set
     * @param mixed $value the value to set for the specified key
     *
     * @return void
     *
     * @throws InvalidArgumentException if the key is invalid
     * @throws RuntimeException if a persistent write fails; the local change is retained
     */
    public function set(array|ConfigInterface|string $key, mixed $value = null): void
    {
        $config = $this->getConfig();
        $config->set($key, $value);

        $this->persistConfig($config);
    }

    /**
     * Removes a configuration value by key.
     *
     * This method MUST update the backend when persistent mode is enabled.
     *
     * @param mixed $key the configuration key to remove
     *
     * @return void
     *
     * @throws InvalidArgumentException if the cache key is invalid
     * @throws RuntimeException if a persistent write fails; the local change is retained
     */
    public function remove(mixed $key): void
    {
        $config = $this->getConfig();
        $config->remove($key);

        $this->persistConfig($config);
    }

    /**
     * Persists a local mutation when write-through mode is enabled.
     *
     * @param ConfigInterface $config the locally updated configuration
     *
     * @return void
     *
     * @throws RuntimeException if the backend refuses the write
     */
    private function persistConfig(ConfigInterface $config): void
    {
        if ($this->persistent && ! $this->cache->set($this->cacheKey, $config->toArray(), $this->cacheTtl)) {
            throw new RuntimeException('Unable to persist configuration to the cache.');
        }
    }
}
