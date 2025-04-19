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

namespace FastForward\Config\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Class ContainerNotFoundException.
 *
 * Exception thrown when a configuration key is not found in the container.
 * This class MUST implement the PSR-11 NotFoundExceptionInterface.
 */
final class ContainerNotFoundException extends \Exception implements NotFoundExceptionInterface
{
    /**
     * Creates a new exception instance for a missing configuration key.
     *
     * This factory method SHOULD be used when a key lookup fails in the ConfigContainer.
     *
     * @param string $key the key that was not found
     *
     * @return self a new instance of the exception describing the missing key
     */
    public static function forKey(string $key): self
    {
        return new self(\sprintf('Config key "%s" not found.', $key));
    }
}
