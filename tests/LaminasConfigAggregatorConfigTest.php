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

namespace FastForward\Config\Tests;

use FastForward\Config\ArrayAccessConfigTrait;
use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LamiasConfigAggregatorConfig;
use FastForward\Config\LaminasConfigAggregatorConfig;
use FastForward\Config\LazyLoadConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LaminasConfigAggregatorConfig::class)]
#[CoversClass(LamiasConfigAggregatorConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesTrait(ArrayAccessConfigTrait::class)]
final class LaminasConfigAggregatorConfigTest extends TestCase
{
    /**
     * @return void
     */
    #[Test]
    public function testInvokeAggregatesProviders(): void
    {
        $provider1 = static fn(): array => ['foo' => 'bar'];
        $provider2 = static fn(): array => ['baz' => 'qux'];

        $config = new LaminasConfigAggregatorConfig([$provider1, $provider2]);

        self::assertInstanceOf(ConfigInterface::class, $config);
        self::assertSame([
            'foo' => 'bar',
            'baz' => 'qux',
        ], $config->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testDeprecatedLamiasConfigAggregatorConfigExtendsLaminasConfigAggregatorConfig(): void
    {
        $provider = static fn(): array => ['legacy' => 'value'];

        $config = new LamiasConfigAggregatorConfig([$provider]);

        self::assertInstanceOf(LaminasConfigAggregatorConfig::class, $config);
        self::assertInstanceOf(ConfigInterface::class, $config);
        self::assertSame(['legacy' => 'value'], $config->toArray());
    }
}
