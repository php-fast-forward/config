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
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\RecursiveDirectoryConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RecursiveDirectoryConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(InvalidArgumentException::class)]
final class RecursiveDirectoryConfigTest extends TestCase
{
    #[Test]
    public function testConstructorWillThrowExceptionForUnreadableRootDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        new RecursiveDirectoryConfig('/non/existing/path');
    }

    #[Test]
    public function testConstructorWillAggregateNestedPhpFiles(): void
    {
        $base   = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('recursive_config_', true);
        $nested = $base . '/nested';
        mkdir($nested, 0o777, true);

        file_put_contents($nested . '/config.php', '<?php return ["nested" => true];');
        file_put_contents($base . '/base.php', '<?php return ["base" => 1];');

        $config   = new RecursiveDirectoryConfig($base);
        $resolved = $config();

        unlink($nested . '/config.php');
        unlink($base . '/base.php');
        rmdir($nested);
        rmdir($base);

        self::assertIsArray($resolved->toArray());
        self::assertSame(['base' => 1, 'nested' => true], $resolved->toArray());
    }
}
