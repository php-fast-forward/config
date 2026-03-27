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

/**
 * Trait LazyLoadConfigTrait.
 *
 * Implements lazy-loading behavior for configuration access.
 * This trait MUST be used in classes implementing ConfigInterface to defer configuration instantiation
 * until first usage. It SHALL invoke the implementing class as a callable to obtain the actual config instance.
 */
trait LazyLoadConfigTrait
{
    use ArrayAccessConfigTrait;

    /**
     * @var ConfigInterface|null holds the loaded configuration instance
     */
    private ?ConfigInterface $config = null;

    /**
     * Implementing class MUST define the __invoke() method to return a ConfigInterface instance.
     *
     * @return ConfigInterface the actual configuration instance
     */
    abstract public function __invoke(): ConfigInterface;

    /**
     * Retrieves a configuration value by key.
     *
     * @param string $key the configuration key to retrieve
     * @param mixed|null $default the default value if the key is not found
     *
     * @return mixed the value of the configuration key or the default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getConfig()
            ->get($key, $default);
    }

    /**
     * Checks for existence of a configuration key.
     *
     * @param string $key the configuration key to check
     *
     * @return bool TRUE if the key exists, FALSE otherwise
     */
    public function has(string $key): bool
    {
        return $this->getConfig()
            ->has($key);
    }

    /**
     * Sets configuration data.
     *
     * @param array|ConfigInterface|string $key the key or set of keys/values to set
     * @param mixed|null $value the value to set if a single key is provided
     *
     * @return void
     */
    public function set(array|ConfigInterface|string $key, mixed $value = null): void
    {
        $this->getConfig()
            ->set($key, $value);
    }

    /**
     * Removes a configuration key.
     *
     * @param string $key the configuration key to remove
     *
     * @return void
     */
    public function remove(string $key): void
    {
        $this->getConfig()
            ->remove($key);
    }

    /**
     * Exports the entire configuration to an array.
     *
     * @return array the configuration as an associative array
     */
    public function toArray(): array
    {
        return $this->getConfig()
            ->toArray();
    }

    /**
     * Retrieves an iterator for traversing the configuration data.
     *
     * @return Traversable an iterator over the configuration
     */
    public function getIterator(): Traversable
    {
        return $this->getConfig()
            ->getIterator();
    }

    /**
     * Retrieves or initializes the configuration instance.
     *
     * @return ConfigInterface the lazily-loaded configuration object
     */
    private function getConfig(): ConfigInterface
    {
        if ($this->config) {
            return $this->config;
        }

        $this->config = \call_user_func($this);

        return $this->config;
    }
}
