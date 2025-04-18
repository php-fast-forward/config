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
use Dflydev\DotAccessData\Util;
use FastForward\Config\Exception\InvalidArgumentException;

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
        $this->data = new Data(data: $this->normalizeConfig($config));
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

        if (\is_array($value) && Util::isAssoc($value)) {
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

        if (Util::isAssoc($key)) {
            $key = $this->normalizeConfig($key);
        }

        $this->data->import($key);
    }

    /**
     * Retrieves an iterator for traversing configuration data.
     *
     * This method SHALL provide recursive array iteration.
     *
     * @return \Traversable a recursive array iterator instance
     */
    public function getIterator(): \Traversable
    {
        return new \RecursiveArrayIterator($this->toArray());
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

    /**
     * Normalizes a configuration array using dot notation delimiters.
     *
     * The method SHALL recursively parse keys containing delimiters and convert them into nested arrays.
     *
     * @param array $config the configuration array to normalize
     *
     * @return array the normalized configuration array
     */
    private function normalizeConfig(array $config): array
    {
        $normalized = [];

        $reflectionConst = new \ReflectionClassConstant(Data::class, 'DELIMITERS');
        $delimiters      = $reflectionConst->getValue();

        $delimiterChars    = implode('', $delimiters);
        $delimitersPattern = '/[' . preg_quote($delimiterChars, '/') . ']/';

        foreach ($config as $key => $value) {
            if (\is_array($value)) {
                if (!Util::isAssoc($value)) {
                    continue;
                }

                $value = $this->normalizeConfig($value);
            }

            if (false === strpbrk($key, $delimiterChars)) {
                $normalized[$key] = $value;

                continue;
            }

            $parts     = preg_split($delimitersPattern, $key);
            $current   = &$normalized;
            $lastIndex = \count($parts) - 1;

            foreach ($parts as $index => $part) {
                if ($index !== $lastIndex) {
                    if (!isset($current[$part]) || !\is_array($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];

                    continue;
                }

                if (isset($current[$part]) && \is_array($current[$part]) && \is_array($value)) {
                    $current[$part] = Util::mergeAssocArray($current[$part], $value, Data::MERGE);

                    continue;
                }

                $current[$part] = $value;
            }
        }

        return $normalized;
    }
}
