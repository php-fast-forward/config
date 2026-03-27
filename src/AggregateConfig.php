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

/**
 * Class AggregateConfig.
 *
 * Represents an aggregated configuration loader that combines multiple configuration sources.
 * This class MUST be used to merge multiple configurations into a single, unified configuration structure.
 *
 * It SHALL lazily load and resolve configuration data only when invoked.
 */
final class AggregateConfig implements ConfigInterface
{
    use LazyLoadConfigTrait;

    /**
     * @var ConfigInterface[] A list of configuration providers to be aggregated.
     *                        Each configuration MUST implement ConfigInterface.
     */
    private readonly array $configs;

    /**
     * Constructs a new instance by accepting a variadic list of configuration objects.
     *
     * These configuration objects MUST implement the ConfigInterface.
     *
     * @param ConfigInterface ...$configs One or more configuration instances to aggregate.
     */
    public function __construct(ConfigInterface ...$configs)
    {
        $this->configs = $configs;
    }

    /**
     * Invokes the configuration aggregator.
     *
     * This method SHALL initialize a new ArrayConfig instance and populate it
     * with the values from each provided configuration source.
     *
     * It MUST return a fully merged configuration in the form of a ConfigInterface implementation.
     *
     * @return ConfigInterface the resulting merged configuration object
     */
    public function __invoke(): ConfigInterface
    {
        $arrayConfig = new ArrayConfig();

        foreach ($this->configs as $config) {
            $arrayConfig->set($config->toArray());
        }

        return $arrayConfig;
    }
}
