<?php

declare(strict_types=1);

namespace FastForward\Config\Tests;

use FastForward\Config\AggregateConfig;
use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

#[CoversClass(AggregateConfig::class)]
#[UsesClass(ArrayConfig::class)]
final class AggregateConfigTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function testInvokeWillAggregateAllConfigsIntoOne()
    {
        $data1 = [uniqid() => uniqid()];
        $data2 = [uniqid() => mt_rand(PHP_INT_MIN, PHP_INT_MAX)];

        $config1 = $this->prophesize(ConfigInterface::class);
        $config1->toArray()->willReturn($data1);

        $config2 = $this->prophesize(ConfigInterface::class);
        $config2->toArray()->willReturn($data2);

        $aggregate = new AggregateConfig(
            $config1->reveal(),
            $config2->reveal(),
        );

        $result = $aggregate();

        $this->assertInstanceOf(ArrayConfig::class, $result);
        $this->assertSame(array_merge($data1, $data2), $result->toArray());
    }
}
