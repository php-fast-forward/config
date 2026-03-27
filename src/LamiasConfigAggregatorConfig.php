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

use Laminas\ConfigAggregator\ConfigAggregator;

/**
 * Class LamiasConfigAggregatorConfig.
 *
 * Integrates Laminas\ConfigAggregator for collecting configuration data from multiple providers.
 * This class MUST support optional caching of the merged configuration using a defined file path.
 * It SHALL return an ArrayConfig with the aggregated data upon invocation.
 */
class LamiasConfigAggregatorConfig implements ConfigInterface
{
    use LazyLoadConfigTrait;

    /**
     * Constructs the configuration aggregator.
     *
     * This constructor SHALL accept a set of configuration providers and an optional cache file path.
     * Providers MUST be iterable and yield valid configuration arrays.
     *
     * @param iterable $providers the configuration providers for aggregation
     * @param string|null $cachedConfigFile the optional path to a cached config file
     */
    public function __construct(
        private iterable $providers,
        private ?string $cachedConfigFile = null,
    ) {}

    /**
     * Aggregates configuration from the provided sources and returns a unified configuration object.
     *
     * If a cached config file is provided and exists, it SHALL be used to hydrate the result.
     * Otherwise, all providers SHALL be executed to merge configurations.
     *
     * @return ConfigInterface the resulting configuration object as an ArrayConfig instance
     */
    public function __invoke(): ConfigInterface
    {
        $configAggregator = new ConfigAggregator($this->providers, $this->cachedConfigFile);

        return new ArrayConfig(config: $configAggregator->getMergedConfig());
    }
}
