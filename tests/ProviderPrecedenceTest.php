<?php

declare(strict_types=1);

namespace FastForward\Config\Tests;

use FastForward\Config\AggregateConfig;
use FastForward\Config\ArrayAccessConfigTrait;
use FastForward\Config\ArrayConfig;
use FastForward\Config\DirectoryConfig;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LaminasConfigAggregatorConfig;
use FastForward\Config\LazyLoadConfigTrait;
use FastForward\Config\Tests\Stub\ConfigProvider;
use Generator;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\InvalidConfigProviderException;
use Laminas\Stdlib\ArrayUtils\MergeRemoveKey;
use Laminas\Stdlib\ArrayUtils\MergeReplaceKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

use function FastForward\Config\config;
use function FastForward\Config\configDir;
use function FastForward\Config\configProvider;

#[CoversClass(LaminasConfigAggregatorConfig::class)]
#[UsesClass(AggregateConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(DirectoryConfig::class)]
#[UsesTrait(ArrayAccessConfigTrait::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesFunction('FastForward\Config\config')]
#[UsesFunction('FastForward\Config\configDir')]
#[UsesFunction('FastForward\Config\configProvider')]
final class ProviderPrecedenceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array}>
     */
    public static function sourcePermutations(): iterable
    {
        foreach (['providers', 'generator', 'directory'] as $kind) {
            for ($mask = 0; $mask < 8; ++$mask) {
                $sources = [];

                foreach (['early', 'middle', 'late'] as $index => $value) {
                    $sources[] = 0 === ($mask & (1 << $index))
                        ? [
                            'db.host' => $value,
                            'commands' => [$value, 'duplicate'],
                        ]
                        : [
                            'db' => [
                                'host' => $value,
                            ],
                            'commands' => [$value, 'duplicate'],
                        ];
                }

                yield $kind . '/' . $mask => [$kind, $sources];
            }
        }
    }

    /**
     * @param string $kind
     * @param array $sources
     *
     * @return void
     */
    #[DataProvider('sourcePermutations')]
    public function testLaterNormalizedSourcesKeepPrecedence(string $kind, array $sources): void
    {
        $directory = sys_get_temp_dir() . '/ff-provider-' . bin2hex(random_bytes(8));
        mkdir($directory);

        try {
            if ('directory' === $kind) {
                foreach ($sources as $index => $values) {
                    file_put_contents(
                        $directory . '/' . $index . '.php',
                        '<?php return ' . var_export($values, true) . ';'
                    );
                }

                $result = configDir($directory);
            } elseif ('generator' === $kind) {
                $result = configProvider([static function () use ($sources): Generator {
                    yield from $sources;
                }]);
            } else {
                $result = configProvider(
                    array_map(static fn(array $values): callable => static fn(): array => $values, $sources)
                );
            }

            self::assertSame('late', $result->get('db.host'));
            self::assertSame(config(...$sources)->toArray(), $result->toArray());
            self::assertSame(
                ['early', 'duplicate', 'middle', 'duplicate', 'late', 'duplicate'],
                $result->get('commands')
            );
        } finally {
            foreach (glob($directory . '/*.php') as $file) {
                unlink($file);
            }

            rmdir($directory);
        }
    }

    /**
     * @return void
     */
    public function testSlashPathsAreNormalizedBeforeCrossProviderMerging(): void
    {
        $config = configProvider([
            static fn(): array => [
                'db/host' => 'early',
            ],
            static fn(): array => [
                'db' => [
                    'host' => 'middle',
                ],
            ],
            static fn(): array => [
                'db.host' => 'late',
            ],
        ]);

        self::assertSame('late', $config->get('db/host'));
    }

    /**
     * @return void
     */
    public function testLaminasMergeInstructionsAndNumericCollisionsAreRetained(): void
    {
        $config = configProvider([
            static fn(): array => [
                'remove' => 'value',
                'replace' => ['old'],
                'numeric' => [
                    2 => 'early',
                ],
            ],
            static fn(): array => [
                'remove' => new MergeRemoveKey(),
                'replace' => new MergeReplaceKey(['new']),
                'numeric' => [
                    2 => 'late',
                ],
            ],
        ]);

        self::assertFalse($config->has('remove'));
        self::assertSame(['new'], $config->get('replace'));
        self::assertSame([
            2 => 'early',
            3 => 'late',
        ], $config->get('numeric'));
    }

    /**
     * @return void
     */
    public function testDuplicateProviderIdentityKeepsUpstreamValidation(): void
    {
        $calls = 0;
        $provider = static function () use (&$calls): array {
            ++$calls;

            return [];
        };

        try {
            new ConfigAggregator([$provider, $provider]);
        } catch (InvalidConfigProviderException) {
            self::assertSame(0, $calls);

            $this->expectException(InvalidConfigProviderException::class);
            configProvider([$provider, $provider])->toArray();

            return;
        }

        $calls = 0;
        self::assertSame([], configProvider([$provider, $provider])->toArray());
        self::assertSame(2, $calls);
    }

    /**
     * @return void
     */
    public function testClassProvidersAndInvalidResultsKeepLaminasValidation(): void
    {
        self::assertSame((new ConfigProvider())(), configProvider([ConfigProvider::class])->toArray());

        $this->expectException(InvalidConfigProviderException::class);
        configProvider([
            static fn(): string => 'invalid',
        ])->toArray();
    }

    /**
     * @return void
     */
    public function testExistingFileCacheBypassesProviderResolution(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ff-provider-cache-');
        file_put_contents($file, '<?php return ["cached" => true];');

        try {
            $config = configProvider(['MissingProviderMustNotBeResolved'], $file);

            self::assertTrue($config->get('cached'));
        } finally {
            unlink($file);
        }
    }
}
