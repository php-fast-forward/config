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

use Dflydev\DotAccessData\Data;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;

/**
 * Class ArrayConfig.
 *
 * Provides a configuration management system with dot notation access.
 * This class SHALL encapsulate configuration data using the DotAccessData library.
 * It MUST support nested keys and provide export, iteration, and dynamic update capabilities.
 *
 * @package FastForward\Config
 */
final class ArrayConfig implements ConfigInterface
{
    use ArrayAccessConfigTrait;

    /**
     * @var Data internal configuration storage instance
     */
    private Data $data;

    /**
     * Constructs the ArrayConfig instance.
     *
     * This constructor SHALL initialize the internal configuration store using normalized keys.
     *
     * @param array $config an optional initial configuration array
     */
    public function __construct(array $config = [])
    {
        $this->data = new Data(
            data: ConfigHelper::normalize($config),
        );
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
        return $this->data->has($key);
    }

    /**
     * Retrieves a value from the configuration.
     *
     * If the value is a nested associative array, a new instance of ArrayConfig SHALL be returned.
     * If the key is not found and no default is provided, a MissingPathException SHOULD be thrown.
     *
     * @param string $key     the configuration key to retrieve
     * @param mixed  $default the default value to return if the key does not exist
     *
     * @return mixed|self the configuration value, or a nested ArrayConfig instance
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data->get($key, $default);

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
     * @param array|ConfigInterface|string $key   the configuration key(s) or configuration object
     * @param null|mixed                   $value the value to assign to the specified key, if applicable
     *
     * @throws InvalidArgumentException if the key is not a string when a value is provided
     */
    public function set(array|ConfigInterface|string $key, mixed $value = null): void
    {
        if (!empty($value)) {
            if (!\is_string($key)) {
                throw InvalidArgumentException::forNonStringKeyWithValue();
            }

            $key = [$key => $value];
        }

        if ($key instanceof ConfigInterface) {
            $key = $key->toArray();
        }

        $this->data->import(ConfigHelper::normalize($key));
    }

    /**
     * Removes a configuration key and its associated value.
     *
     * If the key does not exist, this method SHALL do nothing.
     *
     * @param string $key the configuration key to remove
     */
    public function remove(string $key): void
    {
        if ($this->has($key)) {
            $this->data->remove($key);
        }
    }

    /**
     * Retrieves a traversable set of flattened configuration data.
     *
     * This method SHALL return an iterator where each key represents
     * the nested path in dot notation, and each value is the corresponding value.
     *
     * For example:
     * ['database' => ['host' => 'localhost']] becomes ['database.host' => 'localhost'].
     *
     * @return \Traversable<string, mixed> an iterator of flattened key-value pairs
     */
    public function getIterator(): \Traversable
    {
        return ConfigHelper::flatten($this->toArray());
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
        return $this->data->export();
    }
}
