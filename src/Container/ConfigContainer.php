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

namespace FastForward\Config\Container;

use FastForward\Config\ConfigInterface;
use FastForward\Config\Exception\ContainerNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Class ConfigContainer.
 *
 * Provides a PSR-11 compatible container interface for accessing configuration values.
 *
 * This container implementation SHALL resolve configuration keys by prefix using the alias "config".
 * For example, a request for "config.db.host" will attempt to fetch the key "db.host" from the underlying ConfigInterface.
 *
 * Identifiers such as 'config', ConfigInterface::class, and the concrete config class MUST return the configuration instance itself.
 * Requests for unknown or invalid identifiers MUST result in a ContainerNotFoundException.
 *
 * @package FastForward\Config\Container
 */
final class ConfigContainer implements ContainerInterface
{
    /**
     * @const string The standard identifier for retrieving the configuration object.
     */
    public const ALIAS = 'config';

    /**
     * Constructs a new ConfigContainer instance.
     *
     * This constructor SHALL wrap an existing ConfigInterface instance and expose it
     * through PSR-11 `get()` and `has()` methods with namespace-style key resolution.
     *
     * @param ConfigInterface $config the configuration instance to expose as a container
     */
    public function __construct(
        private ConfigInterface $config,
    ) {}

    /**
     * Determines whether the container can return an entry for the given identifier.
     *
     * This method SHALL return true if:
     * - The identifier matches known internal bindings (alias, interface, or class).
     * - The identifier is prefixed with 'config' and corresponds to an existing key in the configuration.
     *
     * @param string $id identifier of the entry to look for
     *
     * @return bool true if the entry can be resolved; false otherwise
     */
    public function has(string $id): bool
    {
        if ($this->isResolvedByContainer($id)) {
            return true;
        }

        if (!str_starts_with($id, self::ALIAS)) {
            return false;
        }

        return $this->config->has(mb_substr($id, mb_strlen(self::ALIAS) + 1));
    }

    /**
     * Retrieves an entry of the container by its identifier.
     *
     * This method SHALL resolve identifiers in the following order:
     * - If the identifier matches 'config', ConfigInterface::class, or the concrete config class,
     *   it SHALL return the ConfigInterface instance itself.
     * - If the identifier is prefixed with 'config.', the suffix SHALL be used to query the configuration.
     *   If the configuration key exists, its value SHALL be returned.
     * - If the identifier cannot be resolved, a ContainerNotFoundException MUST be thrown.
     *
     * @param string $id identifier of the entry to retrieve
     *
     * @return mixed the value associated with the identifier
     *
     * @throws ContainerNotFoundException if the identifier cannot be resolved
     */
    public function get(string $id)
    {
        if (self::class === $id) {
            return $this;
        }

        if ($this->isResolvedByContainer($id)) {
            return $this->config;
        }

        if (str_starts_with($id, self::ALIAS)) {
            $id = mb_substr($id, mb_strlen(self::ALIAS) + 1);

            if ($this->config->has($id)) {
                return $this->config->get($id);
            }
        }

        throw ContainerNotFoundException::forKey($id);
    }

    /**
     * Determines whether the given identifier is resolved internally by the container itself.
     *
     * This method SHALL match the identifier against:
     * - the alias "config"
     * - the ConfigInterface::class string
     * - the concrete class of the injected configuration instance
     *
     * @param string $id the identifier to check
     *
     * @return bool true if the identifier is resolved internally; false otherwise
     */
    private function isResolvedByContainer(string $id): bool
    {
        return \in_array($id, [self::ALIAS, ConfigInterface::class, $this->config::class], true);
    }
}
