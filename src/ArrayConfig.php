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

use Traversable;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;

/**
 * Class ArrayConfig.
 *
 * Provides a configuration management system with dot notation access.
 * This class SHALL encapsulate configuration data using native PHP arrays.
 * It MUST support nested keys and provide export, iteration, and dynamic update capabilities.
 */
final class ArrayConfig implements ConfigInterface
{
    use ArrayAccessConfigTrait;

    /**
     * @var array internal configuration storage
     */
    private array $data;

    /**
     * Constructs the ArrayConfig instance.
     *
     * This constructor SHALL initialize the internal configuration store using normalized keys.
     *
     * @param array $config an optional initial configuration array
     */
    public function __construct(array $config = [])
    {
        $this->data = ConfigHelper::normalize($config);
    }

    /**
     * Determines whether the given configuration key exists.
     *
     * @param string $key the dot-notation configuration key to check
     *
     * @return bool TRUE if the key exists, FALSE otherwise
     */
    public function has(string $key): bool
    {
        $value = $this->data;

        foreach (ConfigHelper::path($key) as $part) {
            if (! \is_array($value) || ! \array_key_exists($part, $value)) {
                return false;
            }

            $value = $value[$part];
        }

        return true;
    }

    /**
     * Retrieves a value from the configuration.
     *
     * If the value is a nested associative array, a new instance of ArrayConfig SHALL be returned.
     * If the key is missing or its value is null, the default SHALL be returned.
     *
     * @param string $key the configuration key to retrieve
     * @param mixed $default the default value to return if the key does not exist
     *
     * @return mixed|self the configuration value, or a nested ArrayConfig instance
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (ConfigHelper::path($key) as $part) {
            if (! \is_array($value) || ! \array_key_exists($part, $value)) {
                $value = null;

                break;
            }

            $value = $value[$part];
        }

        $value ??= $default;

        if (ConfigHelper::isAssoc($value)) {
            return new self($value);
        }

        return $value;
    }

    /**
     * Sets configuration values into the internal data store.
     *
     * If a value is provided, the key MUST be a string. If the input is an associative array
     * or another ConfigInterface instance, it SHALL be normalized before insertion.
     *
     * @param array|ConfigInterface|string $key the configuration key(s) or configuration object
     * @param mixed|null $value the value to assign to the specified key, if applicable
     *
     * @return void
     *
     * @throws InvalidArgumentException if the key is not a string when a value is provided
     */
    public function set(array|ConfigInterface|string $key, mixed $value = null): void
    {
        if (null !== $value || \is_string($key)) {
            if (! \is_string($key)) {
                throw InvalidArgumentException::forNonStringKeyWithValue();
            }

            $key = [
                $key => $value,
            ];
        }

        if ($key instanceof ConfigInterface) {
            $key = $key->toArray();
        }

        $this->data = array_replace_recursive($this->data, ConfigHelper::normalize($key));
    }

    /**
     * Removes a configuration key and its associated value.
     *
     * If the key does not exist, this method SHALL do nothing.
     *
     * @param string $key the configuration key to remove
     *
     * @return void
     */
    public function remove(string $key): void
    {
        $parts = ConfigHelper::path($key);
        $last = array_pop($parts);
        $current = &$this->data;

        foreach ($parts as $part) {
            if (! isset($current[$part]) || ! \is_array($current[$part])) {
                return;
            }

            $current = &$current[$part];
        }

        unset($current[$last]);
    }

    /**
     * Retrieves a traversable set of flattened configuration data.
     *
     * This method SHALL return an iterator where each key represents
     * the nested path in dot notation, and each value is the corresponding value.
     * For example ['database' => ['host' => 'localhost']] becomes ['database.host' => 'localhost'].
     *
     * @return Traversable<string, mixed> an iterator of flattened key-value pairs
     */
    public function getIterator(): Traversable
    {
        return ConfigHelper::flatten($this->data);
    }

    /**
     * Converts the entire configuration to an associative array.
     *
     * This method MUST export the configuration in its current state.
     *
     * @return array the exported configuration array
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
