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
use FastForward\Config\CachedConfig;
use FastForward\Config\ConfigInterface;
use FastForward\Config\DirectoryConfig;
use FastForward\Config\LamiasConfigAggregatorConfig;
use FastForward\Config\LazyLoadConfigTrait;
use FastForward\Config\RecursiveDirectoryConfig;
use FastForward\Config\Tests\Stub\ConfigProvider;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\SimpleCache\CacheInterface;

use function FastForward\Config\config;
use function FastForward\Config\configCache;
use function FastForward\Config\configDir;
use function FastForward\Config\configProvider;

/**
 * @internal
 */
#[CoversFunction('FastForward\Config\config')]
#[CoversFunction('FastForward\Config\configCache')]
#[CoversFunction('FastForward\Config\configDir')]
#[CoversFunction('FastForward\Config\configProvider')]
#[UsesClass(AggregateConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(CachedConfig::class)]
#[UsesClass(DirectoryConfig::class)]
#[UsesClass(RecursiveDirectoryConfig::class)]
#[UsesClass(LamiasConfigAggregatorConfig::class)]
#[UsesClass(LazyLoadConfigTrait::class)]
final class FunctionsTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function testConfigAcceptsArrayAndReturnsAggregateConfig(): void
    {
        $data   = [uniqid('key_') => uniqid('val_')];
        $result = config($data);

        self::assertInstanceOf(ConfigInterface::class, $result);
        self::assertSame($data, $result->toArray());
    }

    #[Test]
    public function testConfigAcceptsInvokableClassName(): void
    {
        $result = config(ConfigProvider::class);

        self::assertInstanceOf(ConfigInterface::class, $result);
        self::assertSame(['key' => 'value'], $result->toArray());
    }

    #[Test]
    public function testConfigCacheWrapsConfigWithCache(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $data  = [uniqid() => uniqid()];

        $cache->has(Argument::type('string'))->willReturn(false);
        $cache->set(Argument::type('string'), $data)->shouldBeCalledOnce();
        $cache->get(Argument::type('string'))->willReturn($data);

        $config = configCache($cache->reveal(), $data);

        self::assertInstanceOf(CachedConfig::class, $config);
        self::assertInstanceOf(ConfigInterface::class, $config());
    }

    #[Test]
    public function testConfigDirReturnsExpectedInstance(): void
    {
        $dir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('config_', true);
        mkdir($dir);
        file_put_contents($dir . '/test.php', '<?php return ["env" => "test"];');

        $config = configDir($dir, false);

        self::assertInstanceOf(DirectoryConfig::class, $config);

        $recursive = configDir($dir, true);

        self::assertInstanceOf(RecursiveDirectoryConfig::class, $recursive);

        unlink($dir . '/test.php');
        rmdir($dir);
    }

    #[Test]
    public function testConfigProviderReturnsExpectedImplementation(): void
    {
        $provider = new class {
            public function __invoke(): array
            {
                return ['foo' => 'bar'];
            }
        };

        $config = configProvider([$provider]);

        self::assertInstanceOf(LamiasConfigAggregatorConfig::class, $config);
        self::assertSame(['foo' => 'bar'], $config->toArray());
    }
}
