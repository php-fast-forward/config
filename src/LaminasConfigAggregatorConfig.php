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

use FastForward\Config\Helper\ConfigHelper;
use Generator;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\InvalidConfigProviderException;

/**
 * Class LaminasConfigAggregatorConfig.
 *
 * Integrates Laminas\ConfigAggregator for collecting configuration data from multiple providers.
 * This class MUST support optional caching of the merged configuration using a defined file path.
 * It SHALL return an ArrayConfig with the aggregated data upon invocation.
 */
class LaminasConfigAggregatorConfig implements ConfigInterface
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
        $configAggregator = new ConfigAggregator($this->normalizeProviders(), $this->cachedConfigFile);

        return new ArrayConfig(config: $configAggregator->getMergedConfig());
    }

    /**
     * Normalizes each provider result before Laminas applies cross-source merging.
     * Repeated provider identities retain the same wrapper for upstream validation.
     *
     * @return iterable
     */
    private function normalizeProviders(): iterable
    {
        $normalizers = [];

        foreach ($this->providers as $provider) {
            if (! \is_string($provider) && ! \is_callable($provider)) {
                yield $provider;

                continue;
            }

            $identity = match (true) {
                \is_string($provider) => 'class:' . $provider,
                \is_object($provider) => 'object:' . spl_object_id($provider),
                default => null,
            };

            if (null !== $identity && isset($normalizers[$identity])) {
                yield $normalizers[$identity];

                continue;
            }

            $normalizer = static function () use ($provider): mixed {
                if (\is_string($provider)) {
                    if (! class_exists($provider)) {
                        throw InvalidConfigProviderException::fromNamedProvider($provider);
                    }

                    $provider = new $provider();

                    if (! \is_callable($provider)) {
                        throw InvalidConfigProviderException::fromUnsupportedType(get_debug_type($provider));
                    }
                }

                return self::normalizeResult($provider());
            };

            if (null !== $identity) {
                $normalizers[$identity] = $normalizer;
            }

            yield $normalizer;
        }
    }

    /**
     * Normalizes individual arrays yielded by generator-based file providers too.
     *
     * @param mixed $config
     *
     * @return mixed
     */
    private static function normalizeResult(mixed $config): mixed
    {
        if ($config instanceof Generator) {
            return (static function () use ($config): Generator {
                foreach ($config as $values) {
                    yield \is_array($values) ? ConfigHelper::normalize($values) : $values;
                }
            })();
        }

        return \is_array($config) ? ConfigHelper::normalize($config) : $config;
    }
}
