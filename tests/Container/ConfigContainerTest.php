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

namespace FastForward\Config\Tests\Container;

use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use FastForward\Config\Container\ConfigContainer;
use FastForward\Config\Exception\ContainerNotFoundExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConfigContainer::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ContainerNotFoundExceptionInterface::class)]
final class ConfigContainerTest extends TestCase
{
    #[Test]
    public function testHasReturnsTrueForKnownInternalIdentifiers(): void
    {
        $config    = new ArrayConfig();
        $container = new ConfigContainer($config);

        self::assertTrue($container->has(ConfigContainer::ALIAS));
        self::assertTrue($container->has(ConfigInterface::class));
        self::assertTrue($container->has($config::class));
    }

    #[Test]
    public function testHasReturnsTrueIfConfigContainsKey(): void
    {
        $key   = uniqid('key_', true);
        $value = uniqid('val_', true);

        $config    = new ArrayConfig([$key => $value]);
        $container = new ConfigContainer($config);

        self::assertTrue($container->has($key));
    }

    #[Test]
    public function testHasReturnsFalseForUnknownKey(): void
    {
        $config    = new ArrayConfig();
        $container = new ConfigContainer($config);

        self::assertFalse($container->has(uniqid('missing_', true)));
    }

    #[Test]
    public function testGetReturnsContainerForInternalIdentifiers(): void
    {
        $config    = new ArrayConfig();
        $container = new ConfigContainer($config);

        self::assertSame($container, $container->get(ConfigContainer::ALIAS));
        self::assertSame($container, $container->get(ConfigInterface::class));
        self::assertSame($container, $container->get($config::class));
    }

    #[Test]
    public function testGetReturnsConfigValueForKey(): void
    {
        $key   = uniqid('env_', true);
        $value = uniqid('value_', true);

        $config    = new ArrayConfig([$key => $value]);
        $container = new ConfigContainer($config);

        self::assertSame($value, $container->get($key));
    }

    #[Test]
    public function testGetThrowsExceptionForUnknownKey(): void
    {
        $this->expectException(ContainerNotFoundExceptionInterface::class);

        $config    = new ArrayConfig();
        $container = new ConfigContainer($config);

        $container->get(uniqid('unknown_', true));
    }
}
