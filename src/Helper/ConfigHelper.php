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

namespace FastForward\Config\Helper;

use Dflydev\DotAccessData\Data;
use Dflydev\DotAccessData\DataInterface;
use Dflydev\DotAccessData\Util;

/**
 * Class ConfigHelper.
 *
 * Provides a set of static helper methods for manipulating configuration arrays,
 * particularly handling associative arrays with dot notation and nested structures.
 * This class SHALL NOT be instantiated and MUST be used statically.
 */
final class ConfigHelper
{
    /**
     * ConfigHelper constructor.
     *
     * This constructor is private to prevent instantiation of the class.
     * The class MUST be used in a static context only.
     *
     * @codeCoverageIgnore
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
        return \is_array($value) && Util::isAssoc($value);
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
        if (!self::isAssoc($config)) {
            return $config;
        }

        $normalized = [];

        $reflectionConst = new \ReflectionClassConstant(Data::class, 'DELIMITERS');
        $delimiters      = $reflectionConst->getValue();

        $delimiterChars    = implode('', $delimiters);
        $delimitersPattern = '/[' . preg_quote($delimiterChars, '/') . ']/';

        foreach ($config as $key => $value) {
            if (self::isAssoc($value)) {
                $value = self::normalize($value);
            }

            if (!\is_string($key) || false === strpbrk($key, $delimiterChars)) {
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
                    $current[$part] = Util::mergeAssocArray($current[$part], $value, DataInterface::MERGE);

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
     *
     * For example:
     * Input: ['database' => ['host' => 'localhost']]
     * Output: ['database.host' => 'localhost']
     *
     * @param array  $config  the configuration array to flatten
     * @param string $rootKey (Optional) The root key prefix for recursive calls
     *
     * @return \Traversable<string, mixed> a traversable list of flattened key-value pairs
     */
    public static function flatten(array $config, string $rootKey = ''): \Traversable
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
