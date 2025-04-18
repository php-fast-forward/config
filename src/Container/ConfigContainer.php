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
use FastForward\Config\Exception\ContainerNotFoundExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Class ConfigContainer.
 *
 * Provides a PSR-11 compatible container interface for accessing configuration values.
 * This container MAY be used in dependency injection systems where configuration keys
 * should be resolvable via standard container access.
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
     * through PSR-11 `get()` and `has()` methods.
     *
     * @param ConfigInterface $config the configuration instance to expose as a container
     */
    public function __construct(
        private ConfigInterface $config,
    ) {}

    /**
     * Determines if the container can return an entry for the given identifier.
     *
     * @param string $id identifier of the entry to look for
     *
     * @return bool TRUE if the entry is known or exists in the configuration, FALSE otherwise
     */
    public function has(string $id): bool
    {
        return $this->isResolvedByContainer($id)
            || $this->config->has($id);
    }

    /**
     * Retrieves an entry of the container by its identifier.
     *
     * If the ID matches the container itself or the config class, it SHALL return itself.
     * Otherwise, it SHALL retrieve the value from the configuration.
     * If the key is not found, it MUST throw a ContainerNotFoundExceptionInterface.
     *
     * @param string $id identifier of the entry to retrieve
     *
     * @return mixed the value associated with the identifier
     *
     * @throws ContainerNotFoundExceptionInterface if the identifier is not found
     */
    public function get(string $id)
    {
        if ($this->isResolvedByContainer($id)) {
            return $this;
        }

        if ($this->config->has($id)) {
            return $this->config->get($id);
        }

        throw ContainerNotFoundExceptionInterface::forKey($id);
    }

    /**
     * Determines whether the given identifier is resolved internally by the container itself.
     *
     * This method SHALL be used to check if the requested identifier corresponds to:
     * - the container alias (e.g., 'config'),
     * - the Config interface (`FastForward\Config\ConfigInterface`),
     * - or the concrete configuration key.
     *
     * If any of these conditions match, the container MUST resolve the request by returning itself.
     *
     * @param string $id the identifier being checked for internal resolution
     *
     * @return bool TRUE if the container SHALL resolve the identifier itself; FALSE otherwise
     */
    private function isResolvedByContainer(string $id): bool
    {
        return \in_array($id, [self::ALIAS, ConfigInterface::class, $this->config::class], true);
    }
}
