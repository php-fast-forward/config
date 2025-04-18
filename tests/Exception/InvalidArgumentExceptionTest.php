<?php

declare(strict_types=1);

namespace FastForward\Config\Tests\Exception;

use FastForward\Config\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidArgumentException::class)]
final class InvalidArgumentExceptionTest extends TestCase
{
    #[Test]
    public function testForNonStringKeyWithValueWillReturnExpectedMessage()
    {
        $exception = InvalidArgumentException::forNonStringKeyWithValue();

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame(
            'The key must be a string when a value is provided.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function testForUnreadableDirectoryWillReturnExpectedMessage()
    {
        $directory = '/invalid/directory/path';
        $exception = InvalidArgumentException::forUnreadableDirectory($directory);

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame(
            sprintf('The directory "%s" does not exist or is not readable.', $directory),
            $exception->getMessage(),
        );
    }
}
