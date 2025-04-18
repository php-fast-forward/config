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

namespace FastForward\Config\Tests;

use FastForward\Config\ArrayConfig;
use FastForward\Config\DirectoryConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DirectoryConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(InvalidArgumentException::class)]
final class DirectoryConfigTest extends TestCase
{
    #[Test]
    public function testConstructorWillThrowExceptionForUnreadableDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        new DirectoryConfig(uniqid('/path/that/does/not/exist/', true));
    }

    #[Test]
    public function testConstructorWillSucceedForValidDirectory(): void
    {
        $dir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('config_', true);
        mkdir($dir);
        file_put_contents($dir . '/config.php', '<?php return ["foo" => "bar"];');

        $config   = new DirectoryConfig($dir);
        $resolved = $config();

        unlink($dir . '/config.php');
        rmdir($dir);

        self::assertIsArray($resolved->toArray());
        self::assertArrayHasKey('foo', $resolved->toArray());
    }
}
