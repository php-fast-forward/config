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
use FastForward\Config\RecursiveDirectoryConfig;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

use function FastForward\Config\config;
use function FastForward\Config\configDir;

/**
 * @internal
 */
#[CoversClass(DirectoryConfig::class)]
#[CoversClass(RecursiveDirectoryConfig::class)]
#[UsesClass(AggregateConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(LaminasConfigAggregatorConfig::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesFunction('FastForward\Config\config')]
#[UsesFunction('FastForward\Config\configDir')]
final class DirectoryDiscoveryTest extends TestCase
{
    private string $directory;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ff-directory-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->directory);
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('entrypoints')]
    public function testLoadsEveryDepthOnlyWhenRecursive(string $entrypoint): void
    {
        $levels = ['', 'one/', 'one/two/', 'one/two/three/'];
        foreach ($levels as $depth => $path) {
            $this->writeConfig($path . 'app.php', [
                'level' . $depth => true,
                'winner' => $depth,
                'items' => [$depth],
            ]);
        }

        $expected = [
            'level0' => true,
            'winner' => 0,
            'items' => [0],
        ];
        if ($this->isRecursive($entrypoint)) {
            $expected = [
                'level0' => true,
                'winner' => 3,
                'items' => [0, 1, 2, 3],
                'level1' => true,
                'level2' => true,
                'level3' => true,
            ];
        }

        self::assertSame($expected, $this->createConfig($entrypoint, $this->directory)->toArray());
    }

    /**
     * @param string $entrypoint
     * @param string $name
     * @param string $sibling
     * @param bool $unixOnly
     *
     * @return void
     */
    #[Test]
    #[DataProvider('literalPaths')]
    public function testDirectoryNamesAreLiteral(
        string $entrypoint,
        string $name,
        string $sibling,
        bool $unixOnly
    ): void {
        if ($unixOnly && '\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('This filename contains characters reserved on Windows.');
        }

        $this->writeConfig($name . '/app.php', [
            'source' => 'selected',
        ]);
        $this->writeConfig($name . '/nested/deep/app.php', [
            'deep' => true,
        ]);
        $this->writeConfig($sibling . '/app.php', [
            'source' => 'sibling',
            'outside' => true,
        ]);
        $selected = $this->directory . '/' . $name . \DIRECTORY_SEPARATOR;
        $result = $this->createConfig($entrypoint, $selected);

        self::assertSame('selected', $result->get('source'));
        self::assertFalse($result->has('outside'));
        self::assertSame($this->isRecursive($entrypoint), $result->has('deep'));
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('recursiveEntrypoints')]
    public function testOrderingIsDepthThenPathAndPreservesListMerging(string $entrypoint): void
    {
        foreach ([
            'z.php' => 'root-z',
            'a.php' => 'root-a',
            'a/z.php' => 'child-a',
            'a-b/z.php' => 'child-a-b',
            'a/deep/z.php' => 'deep',
        ] as $path => $name) {
            $this->writeConfig($path, [
                'shared' => [
                    'winner' => $name,
                    $name => true,
                ],
                'items' => [$name, 'duplicate'],
            ]);
        }

        $result = $this->createConfig($entrypoint, $this->directory)
            ->toArray();

        self::assertSame('deep', $result['shared']['winner']);
        self::assertCount(6, $result['shared']);
        self::assertSame([
            'root-a', 'duplicate',
            'root-z', 'duplicate',
            'child-a-b', 'duplicate',
            'child-a', 'duplicate',
            'deep', 'duplicate',
        ], $result['items']);
    }

    /**
     * @return void
     */
    #[Test]
    public function testProtectedFilenamePatternStillControlsSelection(): void
    {
        $this->writeConfig('config[custom]/a.php', [
            'unselected' => true,
        ]);
        $this->writeConfig('config[custom]/profiles/z.special.php', [
            'selected' => 'z',
            'items' => ['z'],
        ]);
        $this->writeConfig('config[custom]/profiles/a.special.php', [
            'selected' => 'a',
            'items' => ['a'],
        ]);
        $config = new class ($this->directory . '/config[custom]') extends DirectoryConfig {
            protected const PATTERN = 'profiles/{z*,a*}.special.php';
        };

        self::assertSame([
            'selected' => 'a',
            'items' => ['z', 'a'],
        ], $config->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testFilenamePatternDoesNotLoadSiblingDirectories(): void
    {
        $this->writeConfig('selected/app.php', [
            'selected' => true,
        ]);
        $this->writeConfig('outside/app.php', [
            'outside' => true,
        ]);
        $config = new class ($this->directory . '/selected') extends DirectoryConfig {
            protected const PATTERN = '../outside/*.php';
        };

        self::assertSame([], $config->toArray());
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('entrypoints')]
    public function testRelativeDirectoryDoesNotLoadAnIncludePathNamesake(string $entrypoint): void
    {
        $this->writeConfig('app/config/app.php', [
            'source' => 'selected',
        ]);
        $this->writeConfig('shadow/config/app.php', [
            'source' => 'shadow',
            'outside' => true,
        ]);
        $workingDirectory = getcwd();
        $includePath = get_include_path();
        self::assertIsString($workingDirectory);

        try {
            chdir($this->directory . '/app');
            set_include_path($this->directory . '/shadow');

            self::assertSame([
                'source' => 'selected',
            ], $this->createConfig($entrypoint, 'config')->toArray());
        } finally {
            chdir($workingDirectory);
            set_include_path($includePath);
        }
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('entrypoints')]
    public function testWorkingDirectoryChangesDoNotRedirectLaterIncludes(string $entrypoint): void
    {
        $this->writeConfig('app/config/a.php', []);
        $this->writeConfig('app/config/b.php', [
            'items' => ['second'],
        ]);
        $this->writeConfig('shadow/config/b.php', [
            'items' => ['outside'],
        ]);
        file_put_contents(
            $this->directory . '/app/config/a.php',
            '<?php chdir(' . var_export($this->directory . '/shadow', true) . '); return ["items" => ["first"]];',
        );
        $workingDirectory = getcwd();
        $includePath = get_include_path();
        self::assertIsString($workingDirectory);

        try {
            chdir($this->directory . '/app');
            set_include_path('.');

            self::assertSame([
                'items' => ['first', 'second'],
            ], $this->createConfig($entrypoint, 'config')
                ->toArray());
        } finally {
            chdir($workingDirectory);
            set_include_path($includePath);
        }
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('entrypoints')]
    public function testSymlinkedDirectoriesAreNotTraversedAndFilesAreIncludedOnce(string $entrypoint): void
    {
        if (! \function_exists('symlink')) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        $this->writeConfig('real.php', [
            'items' => ['file'],
        ]);
        $this->writeConfig('one/child.php', [
            'items' => ['child'],
        ]);
        symlink($this->directory . '/real.php', $this->directory . '/alias.php');
        symlink($this->directory . '/one', $this->directory . '/directory-alias');
        symlink($this->directory, $this->directory . '/one/cycle');
        $result = $this->createConfig($entrypoint, $this->directory);

        self::assertSame($this->isRecursive($entrypoint) ? ['file', 'child'] : ['file'], $result->get('items'));
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('entrypoints')]
    public function testDiscoveryAndFileExecutionRemainLazy(string $entrypoint): void
    {
        $this->writeConfig('app.php', [
            'value' => 'before',
        ]);
        $config = $this->createConfig($entrypoint, $this->directory);
        $this->writeConfig('app.php', [
            'value' => 'after',
        ]);
        $this->writeConfig('added.php', [
            'added' => true,
        ]);

        self::assertSame('after', $config->get('value'));
        self::assertTrue($config->get('added'));
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('recursiveEntrypoints')]
    public function testUnreadableChildDoesNotHideAccessibleConfiguration(string $entrypoint): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('chmod does not reproduce POSIX directory read permissions on Windows.');
        }

        $this->writeConfig('root.php', [
            'root' => true,
            'items' => ['root'],
        ]);
        $this->writeConfig('readable/deep/app.php', [
            'deep' => true,
            'items' => ['deep'],
        ]);
        $this->writeConfig('unreadable/app.php', [
            'unreadable' => true,
        ]);
        $config = $this->createConfig($entrypoint, $this->directory);
        $unreadable = $this->directory . '/unreadable';
        chmod($unreadable, 0o000);
        clearstatcache(true, $unreadable);

        try {
            if (is_readable($unreadable)) {
                self::markTestSkipped('The current process can still read mode-000 directories, for example as root.');
            }

            self::assertSame([
                'root' => true,
                'items' => ['root', 'deep'],
                'deep' => true,
            ], $config->toArray());
        } finally {
            chmod($unreadable, 0o700);
            clearstatcache(true, $unreadable);
        }
    }

    /**
     * @param string $entrypoint
     *
     * @return void
     */
    #[Test]
    #[DataProvider('recursiveEntrypoints')]
    public function testRootReadFailureAfterConstructionIsNotHidden(string $entrypoint): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('chmod does not reproduce POSIX directory read permissions on Windows.');
        }

        $config = $this->createConfig($entrypoint, $this->directory);
        chmod($this->directory, 0o000);
        clearstatcache(true, $this->directory);

        try {
            if (is_readable($this->directory)) {
                self::markTestSkipped('The current process can still read mode-000 directories, for example as root.');
            }

            $this->expectException(UnexpectedValueException::class);
            $config->toArray();
        } finally {
            chmod($this->directory, 0o700);
            clearstatcache(true, $this->directory);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testArrayFilesAndWarmFileCacheRemainSupported(): void
    {
        $file = $this->directory . '/provider.php';
        $this->writeConfig('provider.php', [
            'config_cache_enabled' => true,
            'items' => ['one', 'two'],
        ]);
        $cacheFile = $this->directory . '/.cached.php';
        $config = new RecursiveDirectoryConfig($this->directory, $cacheFile);

        self::assertSame(['one', 'two'], $config->get('items'));
        self::assertFileExists($cacheFile);

        file_put_contents($file, '<?php invalid PHP syntax');
        $cached = new RecursiveDirectoryConfig($this->directory, $cacheFile);

        self::assertSame(['one', 'two'], $cached->get('items'));
    }

    /**
     * @return iterable
     */
    public static function entrypoints(): iterable
    {
        foreach (['directory', 'directory-helper', 'recursive', 'recursive-helper', 'config-helper'] as $entrypoint) {
            yield $entrypoint => [$entrypoint];
        }
    }

    /**
     * @return iterable
     */
    public static function recursiveEntrypoints(): iterable
    {
        foreach (['recursive', 'recursive-helper', 'config-helper'] as $entrypoint) {
            yield $entrypoint => [$entrypoint];
        }
    }

    /**
     * @return iterable
     */
    public static function literalPaths(): iterable
    {
        foreach (self::entrypoints() as [$entrypoint]) {
            foreach ([
                ['config[1]', 'config1', false],
                ['config{one,two}', 'configone', false],
                ['config*literal', 'configXliteral', true],
                ['config?literal', 'configXliteral', true],
                ['config\\literal', 'configliteral', true],
                ['configuração[um]', 'configuraçãou', false],
            ] as [$name, $sibling, $unixOnly]) {
                yield $entrypoint . ':' . $name => [$entrypoint, $name, $sibling, $unixOnly];
            }
        }
    }

    /**
     * @param string $entrypoint
     * @param string $directory
     *
     * @return ConfigInterface
     */
    private function createConfig(string $entrypoint, string $directory): ConfigInterface
    {
        return match ($entrypoint) {
            'directory' => new DirectoryConfig($directory),
            'directory-helper' => configDir($directory),
            'recursive' => new RecursiveDirectoryConfig($directory),
            'recursive-helper' => configDir($directory, recursive: true),
            'config-helper' => config($directory),
        };
    }

    /**
     * @param string $entrypoint
     *
     * @return bool
     */
    private function isRecursive(string $entrypoint): bool
    {
        return ! \in_array($entrypoint, ['directory', 'directory-helper'], true);
    }

    /**
     * @param string $path
     * @param array $data
     *
     * @return void
     */
    private function writeConfig(string $path, array $data): void
    {
        $file = $this->directory . '/' . $path;
        if (! is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o777, true);
        }

        file_put_contents($file, '<?php return ' . var_export($data, true) . ';');
    }
}
