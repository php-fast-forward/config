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

use FastForward\Config\Exception\ContainerNotFoundExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @internal
 */
#[CoversClass(ContainerNotFoundExceptionInterface::class)]
final class ContainerNotFoundExceptionInterfaceTest extends TestCase
{
    #[Test]
    public function testForKeyReturnsExpectedMessage(): void
    {
        $key = uniqid('missing_', true);

        $exception = ContainerNotFoundExceptionInterface::forKey($key);

        self::assertInstanceOf(ContainerNotFoundExceptionInterface::class, $exception);
        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        self::assertSame(\sprintf('Config key "%s" not found.', $key), $exception->getMessage());
    }
}
