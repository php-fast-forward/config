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
use FastForward\Config\DirectoryConfig;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LaminasConfigAggregatorConfig;
use FastForward\Config\LazyLoadConfigTrait;
use FastForward\Config\RecursiveDirectoryConfig;
use FilesystemIterator;
use Laminas\ConfigAggregator\ConfigAggregator;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

use function FastForward\Config\configProvider;

/**
 * @internal
 */
#[CoversClass(LaminasConfigAggregatorConfig::class)]
#[CoversClass(DirectoryConfig::class)]
#[CoversClass(RecursiveDirectoryConfig::class)]
#[CoversFunction('FastForward\Config\configProvider')]
#[CoversFunction('FastForward\Config\configDir')]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesTrait(ArrayAccessConfigTrait::class)]
final class FileCacheTest extends TestCase
{
    private string $directory;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/config_file_cache_' . uniqid('', true);
        mkdir($this->directory);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerExamples(): iterable
    {
        yield 'caching guide' => ['advanced/caching.rst', 'Caching Provider Or Directory Aggregation To A File'];
        yield 'provider guide' => ['advanced/providers.rst', 'Using A Cache File'];
    }

    /**
     * @param string $document
     * @param string $heading
     *
     * @return void
     */
    #[Test]
    #[DataProvider('providerExamples')]
    public function testDocumentedProviderExamplesGenerateAndReuseCache(string $document, string $heading): void
    {
        $source = $this->documentedPhpExample($document, $heading, 0);
        $data = $this->executeExample($source);
        $cacheFile = $this->directory . '/var/cache/providers.php';

        self::assertTrue($data[ConfigAggregator::ENABLE_CACHE]);
        self::assertFileExists($cacheFile);
        self::assertSame($data, require $cacheFile);

        $cached = configProvider(
            [
                static fn(): array => throw new LogicException('A cached result must bypass providers.'),
            ],
            cachedConfigFile: $cacheFile,
        );

        self::assertSame($data, $cached->toArray());

        $uncached = configProvider([
            static fn(): array => [
                'source' => 'fresh',
            ],
        ]);

        self::assertSame([
            'source' => 'fresh',
        ], $uncached->toArray());
        self::assertSame($data, require $cacheFile);
    }

    /**
     * @return iterable<string, array{string, string, int, int}>
     */
    public static function directoryExamples(): iterable
    {
        yield 'caching guide' => ['advanced/caching.rst', 'Caching Provider Or Directory Aggregation To A File', 1, 2];
        yield 'directory guide' => ['usage/directory-structure.rst', 'Adding A Cache File', 0, 1];
    }

    /**
     * @param string $document
     * @param string $heading
     * @param int $flagIndex
     * @param int $loaderIndex
     *
     * @return void
     */
    #[Test]
    #[DataProvider('directoryExamples')]
    public function testDocumentedDirectoryExamplesGenerateAndReuseCache(
        string $document,
        string $heading,
        int $flagIndex,
        int $loaderIndex
    ): void {
        mkdir($this->directory . '/config');
        file_put_contents(
            $this->directory . '/config/cache.php',
            $this->documentedPhpExample($document, $heading, $flagIndex)
        );
        file_put_contents($this->directory . '/config/app.php', '<?php return ["app" => ["name" => "Example App"]];');

        $loader = $this->documentedPhpExample($document, $heading, $loaderIndex);
        $data = $this->executeExample($loader);
        $cacheFile = $this->directory . '/var/cache/config.php';

        self::assertTrue($data[ConfigAggregator::ENABLE_CACHE]);
        self::assertSame('Example App', $data['app']['name']);
        self::assertFileExists($cacheFile);
        self::assertSame($data, require $cacheFile);

        file_put_contents(
            $this->directory . '/config/new.php',
            '<?php throw new \LogicException("Cached directories must not be scanned.");'
        );
        self::assertSame($data, $this->executeExample($loader));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function disabledFlags(): iterable
    {
        yield 'missing' => [[]];
        yield 'false' => [[
            ConfigAggregator::ENABLE_CACHE => false,
        ]];
        yield 'zero' => [[
            ConfigAggregator::ENABLE_CACHE => 0,
        ]];
        yield 'null' => [[
            ConfigAggregator::ENABLE_CACHE => null,
        ]];
    }

    /**
     * @param array<string, mixed> $flags
     *
     * @return void
     */
    #[Test]
    #[DataProvider('disabledFlags')]
    public function testAFilePathDoesNotEnableGenerationOnItsOwn(array $flags): void
    {
        $cacheFile = $this->directory . '/providers.php';
        $data = $flags + [
            'app' => true,
        ];
        $config = configProvider([
            static fn(): array => $data,
        ], cachedConfigFile: $cacheFile);

        self::assertSame($data, $config->toArray());
        self::assertFileDoesNotExist($cacheFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testDocumentedDisabledExampleDoesNotGenerateCache(): void
    {
        mkdir($this->directory . '/var/cache', 0o777, true);
        $source = $this->documentedPhpExample(
            'advanced/caching.rst',
            'Caching Provider Or Directory Aggregation To A File',
            3
        );
        $data = $this->executeExample($source);

        self::assertFalse($data[ConfigAggregator::ENABLE_CACHE]);
        self::assertFileDoesNotExist($this->directory . '/var/cache/disabled.php');
    }

    /**
     * @return void
     */
    #[Test]
    public function testALaterProviderCanExplicitlyDisableGeneration(): void
    {
        $cacheFile = $this->directory . '/providers.php';
        $config = configProvider([
            static fn(): array => [
                ConfigAggregator::ENABLE_CACHE => true,
            ],
            static fn(): array => [
                ConfigAggregator::ENABLE_CACHE => false,
                'app' => true,
            ],
        ], cachedConfigFile: $cacheFile);

        self::assertSame([
            ConfigAggregator::ENABLE_CACHE => false,
            'app' => true,
        ], $config->toArray());
        self::assertFileDoesNotExist($cacheFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testAnExistingCacheIsReadEvenWhenItsFlagIsFalse(): void
    {
        $cacheFile = $this->directory . '/providers.php';
        $data = [
            ConfigAggregator::ENABLE_CACHE => false,
            'app' => 'cached',
        ];
        file_put_contents($cacheFile, '<?php return ' . var_export($data, true) . ';');

        $config = configProvider(
            [
                static fn(): array => throw new LogicException('Cached providers must not execute.'),
            ],
            cachedConfigFile: $cacheFile,
        );

        self::assertSame($data, $config->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testEnablingGenerationDoesNotCreateAMissingParentDirectory(): void
    {
        $cacheFile = $this->directory . '/missing/providers.php';
        $data = [
            ConfigAggregator::ENABLE_CACHE => true,
            'app' => true,
        ];
        $config = configProvider([
            static fn(): array => $data,
        ], cachedConfigFile: $cacheFile);

        self::assertSame($data, $config->toArray());
        self::assertDirectoryDoesNotExist($this->directory . '/missing');
        self::assertFileDoesNotExist($cacheFile);
    }

    /**
     * @param string $document
     * @param string $heading
     * @param int $index
     *
     * @return string
     */
    private function documentedPhpExample(string $document, string $heading, int $index): string
    {
        $contents = file_get_contents(__DIR__ . '/../docs/' . $document);
        $offset = strpos($contents, $heading . "\n");
        self::assertNotFalse($offset, 'The documented example heading must exist.');

        preg_match_all('~^\.\. code-block:: php\R\R((?:(?: {3}[^\r\n]*)?\R)+)~m', substr($contents, $offset), $matches);
        self::assertArrayHasKey($index, $matches[1], 'The documented PHP example must exist.');
        $source = preg_replace('/^ {3}/m', '', $matches[1][$index]);

        return "<?php\n" . preg_replace('/^<\?php\s*/', '', (string) $source);
    }

    /**
     * @param string $source
     *
     * @return array<array-key, mixed>
     */
    private function executeExample(string $source): array
    {
        $file = $this->directory . '/example.php';
        file_put_contents($file, $source . "\nreturn \$data;\n");

        return (static fn(string $example): array => require $example)($file);
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    private function removeDirectory(string $directory): void
    {
        foreach (new FilesystemIterator($directory) as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                $this->removeDirectory($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
