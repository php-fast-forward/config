<?php

declare(strict_types=1);

namespace FastForward\Config\Tests;

use FastForward\Config\ArrayConfig;
use FastForward\Config\CachedConfig;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(CachedConfig::class)]
#[UsesClass(ArrayConfig::class)]
final class CachedConfigTest extends TestCase
{
    use ProphecyTrait;

    private CacheInterface|ObjectProphecy $cache;
    private ConfigInterface|ObjectProphecy $defaultConfig;
    private CachedConfig $cachedConfig;

    public function setUp(): void
    {
        $this->cache = $this->prophesize(CacheInterface::class);
        $this->defaultConfig = $this->prophesize(ConfigInterface::class);

        $this->cachedConfig = new CachedConfig(
            cache: $this->cache->reveal(),
            defaultConfig: $this->defaultConfig->reveal(),
        );
    }

    #[Test]
    public function testInvokeWillReturnCachedConfigInstanceWhenNotCached()
    {
        $data = [uniqid() => uniqid()];

        $this->cache->has($this->defaultConfig->reveal()::class)->willReturn(false);
        $this->defaultConfig->toArray()->willReturn($data);

        $this->cache->set($this->defaultConfig->reveal()::class, $data)->shouldBeCalled();
        $this->cache->get($this->defaultConfig->reveal()::class)->willReturn($data);

        $result = ($this->cachedConfig)();

        $this->assertInstanceOf(ArrayConfig::class, $result);
        $this->assertSame($data, $result->toArray());
    }

    #[Test]
    public function testInvokeWillReturnCachedConfigInstanceWhenAlreadyCached()
    {
        $data = [uniqid() => mt_rand(PHP_INT_MIN, PHP_INT_MAX)];

        $this->cache->has($this->defaultConfig->reveal()::class)->willReturn(true);
        $this->cache->get($this->defaultConfig->reveal()::class)->willReturn($data);

        $result = ($this->cachedConfig)();

        $this->assertInstanceOf(ArrayConfig::class, $result);
        $this->assertSame($data, $result->toArray());
    }
}
