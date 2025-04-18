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

namespace FastForward\Config\Tests\Exception;

use FastForward\Config\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InvalidArgumentException::class)]
final class InvalidArgumentExceptionTest extends TestCase
{
    #[Test]
    public function testForNonStringKeyWithValueWillReturnExpectedMessage(): void
    {
        $exception = InvalidArgumentException::forNonStringKeyWithValue();

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(
            'The key must be a string when a value is provided.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function testForUnreadableDirectoryWillReturnExpectedMessage(): void
    {
        $directory = '/invalid/directory/path';
        $exception = InvalidArgumentException::forUnreadableDirectory($directory);

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(
            \sprintf('The directory "%s" does not exist or is not readable.', $directory),
            $exception->getMessage(),
        );
    }
}
