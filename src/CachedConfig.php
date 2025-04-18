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
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ConfigInterface $defaultConfig,
    ) {}

    /**
     * Invokes the configuration and returns the cached configuration data.
     *
     * If the configuration has not yet been cached, it MUST be stored in the cache upon first invocation.
     *
     * @return ConfigInterface a ConfigInterface implementation containing the cached configuration data
     */
    public function __invoke(): ConfigInterface
    {
        if (!$this->cache->has($this->defaultConfig::class)) {
            $this->cache->set($this->defaultConfig::class, $this->defaultConfig->toArray());
        }

        return new ArrayConfig($this->cache->get($this->defaultConfig::class));
    }
}
