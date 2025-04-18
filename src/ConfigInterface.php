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

/**
 * Interface ConfigInterface.
 *
 * Defines the contract for configuration storage and access.
 * Implementing classes MUST support key-based configuration retrieval,
 * nested data export, and mutation in a standardized format.
 *
 * This interface SHALL extend the IteratorAggregate interface to allow iteration.
 *
 * Keys MAY use dot notation to access nested structures, e.g., `my.next.key`
 * corresponds to ['my' => ['next' => ['key' => $value]]].
 */
interface ConfigInterface extends \IteratorAggregate
{
    /**
     * Determines if the specified key exists in the configuration.
     *
     * Dot notation MAY be used to check nested keys.
     *
     * @param string $key the configuration key to check
     *
     * @return bool TRUE if the key exists, FALSE otherwise
     */
    public function has(string $key): bool;

    /**
     * Retrieves a value from the configuration by key.
     *
     * Dot notation MAY be used to access nested keys.
     * If the key does not exist, the provided default value MUST be returned.
     * Implementations MAY return complex nested structures or objects.
     *
     * @param string $key     the configuration key to retrieve
     * @param mixed  $default the default value if the key is not present
     *
     * @return mixed the value associated with the key or the default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Sets a configuration value or merges an array or ConfigInterface.
     *
     * Dot notation MAY be used to set values into nested structures.
     * The method MUST support:
     * - Setting a single key/value pair.
     * - Merging an entire associative array.
     * - Merging another ConfigInterface instance.
     *
     * @param array|self|string $key   a configuration key, an array, or another ConfigInterface
     * @param null|mixed        $value the value to assign, if a key is provided
     */
    public function set(array|self|string $key, mixed $value = null): void;

    /**
     * Exports the configuration as a nested associative array.
     *
     * Implementations MUST return a deep array representation of all configuration data.
     *
     * @return array the full configuration array
     */
    public function toArray(): array;
}
