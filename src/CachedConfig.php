<?php

declare(strict_types=1);

/**
 * This file is part of php-fast-forward/config.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/php-fast-forward/config
 * @copyright Copyright (c) 2025 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace FastForward\Config;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class CachedConfig.
 *
 * Provides a cached implementation of a configuration source.
 * This class MUST cache the configuration output of the decorated ConfigInterface instance.
 * It SHALL lazily initialize and retrieve cached configuration data upon invocation.
 */
final class CachedConfig implements ConfigInterface
{
    use LazyLoadConfigTrait;

    /**
     * Constructs a CachedConfig wrapper.
     *
     * This constructor SHALL accept a PSR-16 cache implementation and a configuration instance
     * to be cached. It MUST defer reading and writing the configuration until invoked.
     *
     * @param CacheInterface  $cache         the cache implementation used for storing configuration data
     * @param ConfigInterface $defaultConfig the configuration source to be cached
     * @param bool            $persistent    whether the cache should be persistent or not
     * @param null|string     $cacheKey      the cache key to use for storing the configuration data
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ConfigInterface $defaultConfig,
        private readonly bool $persistent = false,
        private ?string $cacheKey = null,
    ) {
        $this->cacheKey ??= $this->defaultConfig::class;
    }

    /**
     * Invokes the configuration and returns the cached configuration data.
     *
     * If the configuration has not yet been cached, it MUST be stored in the cache upon first invocation.
     * This method MUST return a ConfigInterface implementation containing the cached configuration data.
     *
     * @return ConfigInterface a ConfigInterface implementation containing the cached configuration data
     *
     * @throws InvalidArgumentException if the cache key is invalid
     */
    public function __invoke(): ConfigInterface
    {
        if (!$this->cache->has($this->cacheKey)) {
            $this->cache->set($this->cacheKey, $this->defaultConfig->toArray());
        }

        return new ArrayConfig($this->cache->get($this->cacheKey));
    }

    /**
     * Sets configuration data.
     *
     * This method MUST update the cached configuration data in the cache if the persistent flag is set to true.
     *
     * @param array|ConfigInterface|string $key   the configuration key or an array of key-value pairs to set
     * @param mixed                        $value the value to set for the specified key
     *
     * @throws InvalidArgumentException if the key is invalid
     */
    public function set(array|ConfigInterface|string $key, mixed $value = null): void
    {
        $config = $this->getConfig();
        $config->set($key, $value);

        if ($this->persistent) {
            $this->cache->set($this->cacheKey, $config->toArray());
        }
    }
}
