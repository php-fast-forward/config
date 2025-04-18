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

use FastForward\Config\AggregateConfig;
use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 */
#[CoversClass(AggregateConfig::class)]
#[UsesClass(ArrayConfig::class)]
final class AggregateConfigTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function testInvokeWillAggregateAllConfigsIntoOne(): void
    {
        $data1 = [uniqid() => uniqid()];
        $data2 = [uniqid() => random_int(PHP_INT_MIN, PHP_INT_MAX)];

        $config1 = $this->prophesize(ConfigInterface::class);
        $config1->toArray()->willReturn($data1);

        $config2 = $this->prophesize(ConfigInterface::class);
        $config2->toArray()->willReturn($data2);

        $aggregate = new AggregateConfig(
            $config1->reveal(),
            $config2->reveal(),
        );

        $result = $aggregate();

        self::assertInstanceOf(ArrayConfig::class, $result);
        self::assertSame(array_merge($data1, $data2), $result->toArray());
    }
}
