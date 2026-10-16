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

namespace FastForward\Config\Helper;

use Traversable;
use FastForward\Config\Exception\InvalidArgumentException;

/**
 * Class ConfigHelper.
 *
 * Provides a set of static helper methods for manipulating configuration arrays,
 * particularly handling associative arrays with dot notation and nested structures.
 * This class SHALL NOT be instantiated and MUST be used statically.
 */
final class ConfigHelper
{
    private const array DELIMITERS = ['.', '/'];

    /**
     * This constructor is private to prevent instantiation of the class.
     *
     * The class MUST be used in a static context only.
     */
    private function __construct()
    {
        // Prevent instantiation
    }

    /**
     * Determines if the provided value is an associative array.
     *
     * This method SHALL check whether the given array uses string keys,
     * distinguishing it from indexed arrays.
     *
     * @param mixed $value the value to check
     *
     * @return bool true if the array is associative; false otherwise
     */
    public static function isAssoc(mixed $value): bool
    {
        if (! \is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (\is_int($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Splits a non-empty configuration key, preserving empty path segments.
     *
     * @param string $key the dot- or slash-separated configuration key
     *
     * @return non-empty-list<string> the path segments
     *
     * @throws InvalidArgumentException if the path is empty
     *
     * @internal
     */
    public static function path(string $key): array
    {
        if ('' === $key) {
            throw InvalidArgumentException::forEmptyPath();
        }

        return explode('.', str_replace(self::DELIMITERS, '.', $key));
    }

    /**
     * Merges maps recursively and concatenates sequential lists, retaining duplicates.
     * Later scalar values SHALL replace earlier values. Sparse and mixed arrays SHALL
     * retain their keys and merge matching indexes recursively.
     *
     * @param array $first the existing configuration
     * @param array $second the configuration to merge
     *
     * @return array the merged configuration
     *
     * @internal
     */
    public static function merge(array $first, array $second): array
    {
        if (array_is_list($first) && array_is_list($second)) {
            return array_merge($first, $second);
        }

        foreach ($second as $key => $value) {
            $first[$key] = isset($first[$key]) && \is_array($first[$key]) && \is_array($value)
                ? self::merge($first[$key], $value)
                : $value;
        }

        return $first;
    }

    /**
     * Normalizes a configuration array using dot notation delimiters.
     *
     * This method SHALL recursively convert keys containing delimiters into nested arrays.
     * For example, a key like "database.host" SHALL be transformed into
     * ['database' => ['host' => 'value']].
     *
     * @param array $config the configuration array to normalize
     *
     * @return array the normalized configuration array
     */
    public static function normalize(array $config): array
    {
        if (! self::isAssoc($config)) {
            return $config;
        }

        $normalized = [];

        $delimiterChars = implode('', self::DELIMITERS);

        foreach ($config as $key => $value) {
            if (self::isAssoc($value)) {
                $value = self::normalize($value);
            }

            if (! \is_string($key) || false === strpbrk($key, $delimiterChars)) {
                $normalized[$key] = $value;

                continue;
            }

            $parts     = self::path($key);
            $current   = &$normalized;
            $lastIndex = \count($parts) - 1;

            foreach ($parts as $index => $part) {
                if ($index !== $lastIndex) {
                    if (! isset($current[$part]) || ! \is_array($current[$part])) {
                        $current[$part] = [];
                    }

                    $current = &$current[$part];

                    continue;
                }

                if (isset($current[$part]) && \is_array($current[$part]) && \is_array($value)) {
                    $current[$part] = self::merge($current[$part], $value);

                    continue;
                }

                $current[$part] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Flattens a nested configuration array into a dot-notated traversable set.
     *
     * This method SHALL recursively iterate through the nested array structure
     * and convert it into a flat representation where keys reflect the nested path.
     * A value like ['database' => ['host' => 'localhost']] SHALL be transformed into
     * ['database.host' => 'localhost'].
     *
     * @param array $config the configuration array to flatten
     * @param string $rootKey (Optional) The root key prefix for recursive calls
     *
     * @return Traversable<string, mixed> a traversable list of flattened key-value pairs
     */
    public static function flatten(array $config, string $rootKey = ''): Traversable
    {
        foreach ($config as $key => $value) {
            if (\is_array($value)) {
                yield from self::flatten($value, $rootKey . $key . '.');
            } else {
                yield $rootKey . $key => $value;
            }
        }
    }
}
