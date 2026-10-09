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
use FastForward\Config\ConfigInterface;
use FastForward\Config\DirectoryConfig;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LaminasConfigAggregatorConfig;
use FastForward\Config\LazyLoadConfigTrait;
use FastForward\Config\PhpFileConfig;
use FastForward\Config\RecursiveDirectoryConfig;
use FastForward\Config\Tests\Stub\ListConfigProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function FastForward\Config\config;
use function FastForward\Config\configDir;
use function FastForward\Config\configProvider;

/**
 * @internal
 */
#[CoversClass(AggregateConfig::class)]
#[CoversFunction('FastForward\Config\config')]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(DirectoryConfig::class)]
#[UsesClass(RecursiveDirectoryConfig::class)]
#[UsesClass(PhpFileConfig::class)]
#[UsesClass(LaminasConfigAggregatorConfig::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesFunction('FastForward\Config\configDir')]
#[UsesFunction('FastForward\Config\configProvider')]
final class ConfigAggregationTest extends TestCase
{
    private string $directory;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/config_aggregation_' . uniqid();
        mkdir($this->directory);
        ListConfigProvider::$invocations = 0;
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->directory);
        ListConfigProvider::$invocations = 0;
    }

    /**
     * @param string $kind
     * @param int $count
     */
    #[DataProvider('sourceKinds')]
    public function testAggregatesListsAcrossSourceKinds(string $kind, int $count): void
    {
        $data = [
            [
                'console' => [
                    'commands' => ['A', 'B'],
                ],
                'service_providers' => ['AP', 'BP'],
                'app' => [
                    'env' => 'first',
                    'name' => 'tool',
                ],
                'first_only' => true,
            ],
            [
                'console' => [
                    'commands' => ['C', 'A'],
                ],
                'service_providers' => ['CP', 'AP'],
                'app' => [
                    'env' => 'second',
                    'debug' => true,
                ],
                'second_only' => true,
            ],
            [
                'console' => [
                    'commands' => ['D'],
                ],
                'service_providers' => ['DP'],
                'app' => [
                    'env' => 'third',
                ],
                'third_only' => true,
            ],
        ];
        $sources = [];

        foreach (\array_slice($data, 0, $count) as $index => $values) {
            $sources[] = $this->source($kind, $index, $values);
        }

        $result = config(...$sources);
        $commands = 2 === $count ? ['A', 'B', 'C', 'A'] : ['A', 'B', 'C', 'A', 'D'];
        $providers = 2 === $count ? ['AP', 'BP', 'CP', 'AP'] : ['AP', 'BP', 'CP', 'AP', 'DP'];
        $expected = [
            'console' => [
                'commands' => $commands,
            ],
            'service_providers' => $providers,
            'app' => [
                'env' => 2 === $count ? 'second' : 'third',
                'name' => 'tool',
                'debug' => true,
            ],
            'first_only' => true,
            'second_only' => true,
        ];

        if (3 === $count) {
            $expected['third_only'] = true;
        }

        self::assertSame($commands, $result->get('console.commands'));
        self::assertSame($providers, $result->get('service_providers'));
        self::assertSame($expected, $result->toArray());
        self::assertTrue($result->has('app.name'));
        self::assertSame('tool', $result->get('app')->get('name'));
        self::assertSame('A', iterator_to_array($result)['console.commands.0']);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function sourceKinds(): iterable
    {
        foreach ([
            'arrays',
            'objects',
            'files',
            'file objects',
            'directories',
            'directory objects',
            'providers',
            'mixed',
        ] as $kind) {
            yield $kind . ' / two sources' => [$kind, 2];
            yield $kind . ' / three sources' => [$kind, 3];
        }
    }

    /**
     * @return void
     */
    public function testMatchesConfigProviderWithoutEagerLoading(): void
    {
        $calls = 0;
        $first = static function () use (&$calls): array {
            ++$calls;

            return [
                'console' => [
                    'commands' => ['A', 'B'],
                ],
            ];
        };
        $second = static function () use (&$calls): array {
            ++$calls;

            return [
                'console' => [
                    'commands' => ['C'],
                ],
            ];
        };

        $aggregate = config(configProvider([$first]), configProvider([$second]));
        $providers = configProvider([$first, $second]);
        self::assertSame(0, $calls);
        self::assertSame(['A', 'B', 'C'], $aggregate->get('console.commands'));
        self::assertSame(2, $calls);
        self::assertSame($aggregate->toArray(), $providers->toArray());
        self::assertSame(4, $calls);
        self::assertSame(['A', 'B', 'C'], $aggregate->get('console.commands'));
        self::assertSame(4, $calls);
    }

    /**
     * @return void
     */
    public function testInvokableClassNamesStayLazyAndKeepDuplicates(): void
    {
        $result = config(
            [
                'console' => [
                    'commands' => ['A', 'B'],
                ],
                'service_providers' => ['AP'],
            ],
            ListConfigProvider::class,
            ListConfigProvider::class,
        );

        self::assertSame(0, ListConfigProvider::$invocations);
        self::assertSame(['A', 'B', 'C', 'B', 'C', 'B'], $result->get('console.commands'));
        self::assertSame(2, ListConfigProvider::$invocations);
        self::assertSame(['AP', 'CP', 'CP'], $result->get('service_providers'));
        self::assertSame(2, ListConfigProvider::$invocations);
    }

    /**
     * @return void
     */
    public function testNormalizesDottedAndNestedKeysFromConfigInterfaces(): void
    {
        $source = self::createStub(ConfigInterface::class);
        $source->method('toArray')
            ->willReturn([
                'console' => [
                    'commands' => ['A'],
                    'enabled' => true,
                ],
                'console.commands' => ['B'],
            ]);

        $result = config($source, [
            'console.commands' => ['C'],
            'console.verbose' => false,
        ]);

        self::assertSame(['A', 'B', 'C'], $result->get('console.commands'));
        self::assertTrue($result->get('console.enabled'));
        self::assertFalse($result->get('console.verbose'));
    }

    /**
     * @param mixed $first
     * @param mixed $second
     * @param mixed $expected
     */
    #[DataProvider('boundaryValues')]
    public function testPreservesExistingBoundarySemantics(mixed $first, mixed $second, mixed $expected): void
    {
        $result = config([
            'value' => $first,
        ], [
            'value' => $second,
        ]);

        self::assertTrue($result->has('value'));
        self::assertSame([
            'value' => $expected,
        ], $result->toArray());
    }

    /**
     * @return array<string, array{mixed, mixed, mixed}>
     */
    public static function boundaryValues(): array
    {
        return [
            'empty then list' => [[], ['A'], ['A']],
            'list then empty' => [['A'], [], ['A']],
            'two empty arrays' => [[], [], []],
            'map then empty' => [[
                'key' => 'A',
            ], [], [
                'key' => 'A',
            ]],
            'empty then map' => [[], [
                'key' => 'A',
            ], [
                'key' => 'A',
            ]],
            'sparse numeric arrays' => [[
                2 => 'A',
                5 => 'B',
            ], [
                2 => 'C',
                7 => 'D',
            ], [
                2 => 'C',
                5 => 'B',
                7 => 'D',
            ]],
            'list then sparse' => [['A', 'B'], [
                0 => 'C',
                3 => 'D',
            ], [
                0 => 'C',
                1 => 'B',
                3 => 'D',
            ]],
            'sparse then list' => [[
                2 => 'A',
            ], ['B'], [
                2 => 'A',
                0 => 'B',
            ]],
            'mixed arrays' => [[
                0 => 'A',
                'key' => 'first',
            ], [
                0 => 'B',
                'key' => 'last',
                2 => 'C',
            ], [
                0 => 'B',
                'key' => 'last',
                2 => 'C',
            ]],
            'map then list' => [[
                'key' => 'A',
            ], ['B'], [
                'key' => 'A',
                0 => 'B',
            ]],
            'list then map' => [['A'], [
                'key' => 'B',
            ], [
                0 => 'A',
                'key' => 'B',
            ]],
            'scalar then list' => ['A', ['B'], ['B']],
            'list then scalar' => [['A'], 'B', 'B'],
            'scalar then map' => [
                'A', [
                    'key' => 'B',
                ], [
                    'key' => 'B',
                ]],
            'map then scalar' => [[
                'key' => 'A',
            ], 'B', 'B'],
            'scalar then empty' => ['A', [], []],
            'null then list' => [null, ['A'], ['A']],
            'list then null' => [['A'], null, null],
            'null then scalar' => [null, 'A', 'A'],
            'scalar then null' => ['A', null, null],
            'two nulls' => [null, null, null],
        ];
    }

    /**
     * @param string $kind
     * @param int $index
     * @param array $values
     */
    private function source(string $kind, int $index, array $values): array|ConfigInterface|string
    {
        if ('mixed' === $kind) {
            $kind = ['arrays', 'objects', 'files'][$index];
        }

        if ('arrays' === $kind) {
            return $values;
        }

        if ('objects' === $kind) {
            return new ArrayConfig($values);
        }

        if ('providers' === $kind) {
            return configProvider([
                static fn(): array => $values,
            ]);
        }

        $directory = $this->directory . '/module' . $index;
        mkdir($directory);
        $path = $directory . '/config.php';

        if (str_contains($kind, 'director')) {
            mkdir($directory . '/nested');
            file_put_contents($directory . '/nested/commands.php', '<?php return ' . var_export($values, true) . ';');
            $values = [];
        }

        file_put_contents($path, '<?php return ' . var_export($values, true) . ';');

        return match ($kind) {
            'files' => $path,
            'file objects' => new PhpFileConfig($path),
            'directories' => $directory,
            'directory objects' => configDir($directory, true),
        };
    }
}
