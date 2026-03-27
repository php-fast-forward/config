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

namespace FastForward\Config\Exception;

/**
 * Class InvalidArgumentException.
 *
 * Specialized exception for handling invalid arguments within the configuration context.
 * This class SHALL be used to provide meaningful errors when invalid data is passed
 * to configuration components.
 */
final class InvalidArgumentException extends \InvalidArgumentException
{
    /**
     * Thrown when the key is not a string but a value is provided.
     *
     * @return self the exception indicating the key must be a string
     */
    public static function forNonStringKeyWithValue(): self
    {
        return new self('The key must be a string when a value is provided.');
    }

    /**
     * Thrown when a given directory does not exist or is not readable.
     *
     * @param string $directory the path to the invalid directory
     *
     * @return self the exception indicating an invalid or unreadable directory
     */
    public static function forUnreadableDirectory(string $directory): self
    {
        return new self(\sprintf('The directory "%s" does not exist or is not readable.', $directory));
    }
}
