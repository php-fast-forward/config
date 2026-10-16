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

use FastForward\Config\AggregateConfig;
use FastForward\Config\ArrayConfig;
use FastForward\Config\CachedConfig;
use FastForward\Config\ConfigInterface;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LazyLoadConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Psr16Cache;

use function FastForward\Config\configCache;

/**
 * @internal
 */
#[CoversClass(CachedConfig::class)]
#[UsesClass(AggregateConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesFunction('FastForward\Config\config')]
#[UsesFunction('FastForward\Config\configCache')]
final class CachedConfigTest extends TestCase
{
    /**
     * @return void
     */
    #[Test]
    public function testDefaultKeysArePortableAndIsolateSameClassSources(): void
    {
        $pool = new ArrayAdapter();
        $cache = new Psr16Cache($pool);
        $source = new ArrayConfig([
            'app' => 'A',
        ]);
        $first = new CachedConfig($cache, $source);
        $second = new CachedConfig($cache, new ArrayConfig([
            'app' => 'B',
        ]));

        self::assertSame([
            'app' => 'A',
        ], $first()->toArray());
        self::assertSame([
            'app' => 'B',
        ], $second()->toArray());

        $source->set('app', 'changed');
        self::assertSame([
            'app' => 'A',
        ], $first()->toArray());

        $keys = array_keys($pool->getValues());
        self::assertCount(2, $keys);
        foreach ($keys as $key) {
            self::assertMatchesRegularExpression('/\A[A-Za-z0-9_.]{1,64}\z/', $key);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testDefaultHelperWorksWithStrictCacheAndIsolatesSources(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $first = configCache($cache, [
            'app' => 'A',
        ]);
        $second = configCache($cache, [
            'app' => 'B',
        ]);

        self::assertSame('A', $first->get('app'));
        self::assertSame('B', $second->get('app'));
    }

    /**
     * @return void
     *
     * @throws RuntimeException
     */
    #[Test]
    public function testDefaultKeyDoesNotResolveOrSerializeLazySource(): void
    {
        $source = new class implements ConfigInterface {
            use LazyLoadConfigTrait;

            public int $resolutions = 0;

            /**
             * @return ConfigInterface
             */
            public function __invoke(): ConfigInterface
            {
                ++$this->resolutions;

                return new ArrayConfig([
                    'app' => 'available',
                ]);
            }

            /**
             * @return array
             *
             * @throws RuntimeException
             */
            public function __serialize(): array
            {
                throw new RuntimeException('The lazy source must not be serialized.');
            }
        };
        $config = new CachedConfig(new Psr16Cache(new ArrayAdapter()), $source);

        self::assertSame(0, $source->resolutions);
        self::assertSame([
            'app' => 'available',
        ], $config()->toArray());
        self::assertSame([
            'app' => 'available',
        ], $config()->toArray());
        self::assertSame(1, $source->resolutions);
    }

    /**
     * @return void
     */
    #[Test]
    public function testExplicitKeySharesEntriesWithoutResolvingSourceOnHit(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $writer = new CachedConfig($cache, new ArrayConfig([
            'app' => 'shared',
        ]), cacheKey: 'app-config');
        self::assertSame('shared', $writer->get('app'));

        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::never())->method('toArray');
        $reader = new CachedConfig($cache, $source, cacheKey: 'app-config');

        self::assertSame([
            'app' => 'shared',
        ], $cache->get('app-config'));
        self::assertSame('shared', $reader->get('app'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testEmptyArrayIsAHit(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set('app-config', []);

        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::never())->method('toArray');
        $config = new CachedConfig($cache, $source, cacheKey: 'app-config');

        self::assertSame([], $config->toArray());
    }

    /**
     * @param mixed $entry
     *
     * @return void
     */
    #[Test]
    #[DataProvider('unusableCacheValues')]
    public function testUnusableEntryResolvesSourceWithoutHasOrReadback(mixed $entry): void
    {
        $data = [
            'app' => 'available',
        ];
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('toArray')->willReturn($data);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method('has');
        $cache->expects(self::once())->method('get')->with('app-config')->willReturn($entry);
        $cache->expects(self::once())->method('set')->with('app-config', $data)->willReturn(true);
        $config = new CachedConfig($cache, $source, cacheKey: 'app-config');

        self::assertSame($data, $config->toArray());
    }

    /**
     * @return iterable
     */
    public static function unusableCacheValues(): iterable
    {
        yield 'miss or eviction' => [null];
        yield 'false' => [false];
        yield 'integer' => [1];
        yield 'string' => ['stale'];
        yield 'object' => [new stdClass()];
    }

    /**
     * @return void
     */
    #[Test]
    public function testNonRetainingCacheReturnsSource(): void
    {
        $config = new CachedConfig(
            new Psr16Cache(new NullAdapter()),
            new ArrayConfig([
                'app' => 'available',
            ]),
            cacheKey: 'app-config',
        );

        self::assertSame([
            'app' => 'available',
        ], $config->toArray());
        self::assertSame('available', $config->get('app'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testFailedInitialPublicationReturnsSourceAndRetainsLocalConfig(): void
    {
        $data = [
            'app' => 'available',
        ];
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('toArray')->willReturn($data);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('app-config')->willReturn(null);
        $cache->expects(self::once())->method('set')->with('app-config', $data)->willReturn(false);
        $config = new CachedConfig($cache, $source, persistent: true, cacheKey: 'app-config');

        self::assertSame($data, $config->toArray());
        self::assertSame('available', $config->get('app'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testEvictionResolvesSourceAgain(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $source = new ArrayConfig([
            'app' => 'before',
        ]);
        $config = new CachedConfig($cache, $source, cacheKey: 'app-config');
        self::assertSame('before', $config()->get('app'));

        $cache->delete('app-config');
        $source->set('app', 'after');

        self::assertSame('after', $config()->get('app'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testBackendInvalidKeyExceptionIsPropagated(): void
    {
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::never())->method('toArray');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('invalid:key')
            ->willThrowException(new InvalidArgumentException('Invalid cache key.'));
        $config = new CachedConfig($cache, $source, cacheKey: 'invalid:key');

        $this->expectException(InvalidArgumentException::class);
        $config->toArray();
    }

    /**
     * @param string $mutation
     * @param array $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('mutations')]
    public function testPersistentMutationsUpdateCache(string $mutation, array $expected): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set('app-config', [
            'app' => 'before',
        ]);

        $config = new CachedConfig($cache, new ArrayConfig(), persistent: true, cacheKey: 'app-config');

        $this->mutate($config, $mutation);

        self::assertSame($expected, $config->toArray());
        self::assertSame($expected, $cache->get('app-config'));
    }

    /**
     * @param string $mutation
     * @param array $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('mutations')]
    public function testNonPersistentMutationsOnlyUpdateLocalConfig(string $mutation, array $expected): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set('app-config', [
            'app' => 'before',
        ]);

        $config = new CachedConfig($cache, new ArrayConfig(), cacheKey: 'app-config');

        $this->mutate($config, $mutation);

        self::assertSame($expected, $config->toArray());
        self::assertSame([
            'app' => 'before',
        ], $cache->get('app-config'));
    }

    /**
     * @param string $mutation
     * @param array $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('mutations')]
    public function testFailedPersistentMutationsThrowAndKeepLocalChanges(string $mutation, array $expected): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('app-config')->willReturn([
            'app' => 'before',
        ]);
        $cache->expects(self::once())->method('set')->with('app-config', $expected)->willReturn(false);
        $config = new CachedConfig($cache, new ArrayConfig(), persistent: true, cacheKey: 'app-config');

        try {
            $this->mutate($config, $mutation);
            self::fail('A failed persistent write must be reported.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Unable to persist configuration to the cache.', $runtimeException->getMessage());
        }

        self::assertSame($expected, $config->toArray());
    }

    /**
     * @return iterable
     */
    public static function mutations(): iterable
    {
        yield 'set' => [
            'set', [
                'app' => 'after',
            ]];
        yield 'remove' => ['remove', []];
    }

    /**
     * @param CachedConfig $config
     * @param string $mutation
     *
     * @return void
     */
    private function mutate(CachedConfig $config, string $mutation): void
    {
        if ('set' === $mutation) {
            $config->set('app', 'after');

            return;
        }

        $config->remove('app');
    }
}
