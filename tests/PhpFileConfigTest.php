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

use Composer\Autoload\ClassLoader;
use FastForward\Config\ArrayAccessConfigTrait;
use FastForward\Config\ArrayConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LazyLoadConfigTrait;
use FastForward\Config\PhpFileConfig;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(PhpFileConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(InvalidArgumentException::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesTrait(ArrayAccessConfigTrait::class)]
final class PhpFileConfigTest extends TestCase
{
    private string $tempFile;

    private string $tempDirectory;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('config_', true) . '.php';
        $this->tempDirectory = $this->tempFile . '_dir';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        $lockFile = \dirname($this->tempFile) . '/.' . basename($this->tempFile) . '.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }

        if (is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillReturnArrayConfigFromFile(): void
    {
        $data = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
            'debug' => true,
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($data, true) . ';');

        $config = new PhpFileConfig($this->tempFile);

        self::assertSame($data, $config->toArray());
        self::assertSame('localhost', $config->get('database.host'));
        self::assertTrue($config->has('database.port'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillThrowExceptionWhenFileDoesNotExistAndNoDefault(): void
    {
        $config = new PhpFileConfig($this->tempFile);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The file "%s" does not exist or is not readable.', $this->tempFile));

        $config->toArray();
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillUseDefaultConfigWhenFileDoesNotExist(): void
    {
        $defaultData = [
            'app' => [
                'name' => 'MyApp',
            ],
        ];

        $config = new PhpFileConfig(
            file: $this->tempFile,
            persistent: false,
            defaultConfig: new ArrayConfig($defaultData),
        );

        self::assertSame($defaultData, $config->toArray());
        self::assertFileDoesNotExist($this->tempFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillNotPersistDefaultConfigOnReadEvenWhenPersistentIsTrue(): void
    {
        $defaultData = [
            'app' => [
                'name' => 'PersistedApp',
            ],
        ];

        $config = new PhpFileConfig(file: $this->tempFile, persistent: true, defaultConfig: $defaultData);

        self::assertSame($defaultData, $config->toArray());
        self::assertFileDoesNotExist($this->tempFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillPersistDefaultConfigAndMutationsWhenPersistentIsTrue(): void
    {
        $defaultData = [
            'app' => [
                'name' => 'PersistedApp',
            ],
        ];

        $config = new PhpFileConfig(file: $this->tempFile, persistent: true, defaultConfig: $defaultData);

        $config->set('app.env', 'testing');

        self::assertFileExists($this->tempFile);

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame([
            'app' => [
                'name' => 'PersistedApp',
                'env'  => 'testing',
            ],
        ], $reloaded->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillCreateDirectoryIfItDoesNotExistWhenPersistentIsTrue(): void
    {
        $baseDir  = sys_get_temp_dir() . '/base_' . uniqid();
        $subDir   = $baseDir . '/nested/sub/dir';
        $filePath = $subDir . '/config.php';

        try {
            $config = new PhpFileConfig(
                file: $filePath,
                persistent: true,
                defaultConfig: [
                    'initial' => true,
                ],
            );

            self::assertDirectoryDoesNotExist($subDir);

            $config->set('new_key', 'new_val');

            self::assertDirectoryExists($subDir);
            self::assertFileExists($filePath);

            $reloaded = new PhpFileConfig($filePath);
            self::assertSame([
                'initial' => true,
                'new_key' => 'new_val',
            ], $reloaded->toArray());
        } finally {
            $lockFile = $subDir . '/.config.php.lock';
            if (file_exists($lockFile)) {
                @unlink($lockFile);
            }

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            if (is_dir($subDir)) {
                rmdir($subDir);
                rmdir($baseDir . '/nested/sub');
                rmdir($baseDir . '/nested');
                rmdir($baseDir);
            }
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillThrowExceptionWhenFileDoesNotReturnArray(): void
    {
        file_put_contents($this->tempFile, '<?php return "invalid";');

        $config = new PhpFileConfig($this->tempFile);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The configuration file "%s" must return an array.', $this->tempFile));

        $config->toArray();
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillNotUpdateFileWhenPersistentIsFalse(): void
    {
        $initialData = [
            'key' => 'initial_value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: false);
        $config->set('key', 'new_value');

        self::assertSame('new_value', $config->get('key'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame('initial_value', $reloaded->get('key'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillUpdateFileWhenPersistentIsTrue(): void
    {
        $initialData = [
            'key' => 'initial_value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: true);
        $config->set('key', 'new_value');
        $config->set('nested.setting', 42);

        self::assertSame('new_value', $config->get('key'));
        self::assertSame(42, $config->get('nested.setting'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame('new_value', $reloaded->get('key'));
        self::assertSame(42, $reloaded->get('nested.setting'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testRemoveWillNotUpdateFileWhenPersistentIsFalse(): void
    {
        $initialData = [
            'key' => 'value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: false);
        $config->remove('key');

        self::assertFalse($config->has('key'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertTrue($reloaded->has('key'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testRemoveWillUpdateFileWhenPersistentIsTrue(): void
    {
        $initialData = [
            'key' => 'value',
            'keep' => 'preserved',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: true);
        $config->remove('key');

        self::assertFalse($config->has('key'));
        self::assertTrue($config->has('keep'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertFalse($reloaded->has('key'));
        self::assertTrue($reloaded->has('keep'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testArrayAccessWithPersistence(): void
    {
        $initialData = [
            'key' => 'value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: true);

        self::assertTrue(isset($config['key']));
        self::assertSame('value', $config['key']);

        $config['new_key'] = 'persisted_value';
        unset($config['key']);

        self::assertFalse(isset($config['key']));
        self::assertSame('persisted_value', $config['new_key']);

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertFalse($reloaded->has('key'));
        self::assertSame('persisted_value', $reloaded->get('new_key'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testIterationReturnsFlattenedKeys(): void
    {
        $data = [
            'db' => [
                'host' => '127.0.0.1',
            ],
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($data, true) . ';');

        $config = new PhpFileConfig($this->tempFile);
        $iterated = iterator_to_array($config);

        self::assertSame([
            'db.host' => '127.0.0.1',
        ], $iterated);
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillThrowExceptionWhenPathIsDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/config_dir_' . uniqid();
        mkdir($dir);

        try {
            $config = new PhpFileConfig($dir);
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(\sprintf('The file "%s" does not exist or is not readable.', $dir));
            $config->toArray();
        } finally {
            rmdir($dir);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testDumpWillThrowExceptionWhenDirectoryIsNotWritable(): void
    {
        $unwritableDir = '/proc/nonexistent_' . uniqid() . '/file.php';
        $config = new PhpFileConfig($unwritableDir, persistent: true, defaultConfig: [
            'foo' => 'bar',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The file "%s" is not writable.', $unwritableDir));

        $config->set('foo', 'baz');
    }

    /**
     * @return void
     */
    #[Test]
    public function testDumpWillThrowExceptionWhenFileIsNotWritable(): void
    {
        file_put_contents($this->tempFile, '<?php return ["foo" => "bar"];');
        chmod($this->tempFile, 0o444);

        if (is_writable($this->tempFile)) {
            self::markTestSkipped('Current environment (e.g. running as root) bypasses read-only file permissions.');
        }

        try {
            $config = new PhpFileConfig($this->tempFile, persistent: true);
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(\sprintf('The file "%s" is not writable.', $this->tempFile));

            $config->set('foo', 'baz');
        } finally {
            chmod($this->tempFile, 0o644);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testDumpWillThrowExceptionWhenValueCannotBeExported(): void
    {
        $config = new PhpFileConfig($this->tempFile, persistent: true, defaultConfig: []);

        $resource = fopen('php://memory', 'r');
        $this->expectException(InvalidArgumentException::class);

        try {
            $config->set('resource', $resource);
        } finally {
            if (\is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testPersistentMutationsAcrossMultipleInstancesDoNotOverwriteEachOther(): void
    {
        $instanceA = new PhpFileConfig(
            file: $this->tempFile,
            persistent: true,
            defaultConfig: [
                'initial' => 'value',
            ],
        );

        $instanceB = new PhpFileConfig(
            file: $this->tempFile,
            persistent: true,
            defaultConfig: [
                'initial' => 'value',
            ],
        );

        // Instance A sets key 'foo'
        $instanceA->set('foo', 'from_a');

        // Instance B sets key 'bar'
        $instanceB->set('bar', 'from_b');

        // The persisted file should contain both 'foo' and 'bar'
        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame([
            'initial' => 'value',
            'foo'     => 'from_a',
            'bar'     => 'from_b',
        ], $reloaded->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testPersistentRemoveAcrossMultipleInstancesDoNotOverwriteEachOther(): void
    {
        $instanceA = new PhpFileConfig(
            file: $this->tempFile,
            persistent: true,
            defaultConfig: [
                'initial' => 'value',
                'keep'    => 'remains',
            ],
        );

        $instanceB = new PhpFileConfig(
            file: $this->tempFile,
            persistent: true,
            defaultConfig: [
                'initial' => 'value',
                'keep'    => 'remains',
            ],
        );

        // Instance A removes 'initial'
        $instanceA->remove('initial');

        // Instance B sets key 'extra'
        $instanceB->set('extra', 'added');

        // The persisted file should have 'keep' and 'extra', but not 'initial'
        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame([
            'keep'  => 'remains',
            'extra' => 'added',
        ], $reloaded->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testPersistentMutationPreservesRestrictiveFilePermissions(): void
    {
        file_put_contents($this->tempFile, '<?php return ["secret" => "initial"];');
        chmod($this->tempFile, 0o600);

        $initialPerms = fileperms($this->tempFile) & 0o777;

        $config = new PhpFileConfig($this->tempFile, persistent: true);
        $config->set('secret', 'updated');

        $updatedPerms = fileperms($this->tempFile) & 0o777;
        self::assertSame($initialPerms, $updatedPerms);
        self::assertSame(0o600, $updatedPerms);
    }

    /**
     * @return void
     */
    #[Test]
    public function testPersistentMutationPreservesSymlinkTarget(): void
    {
        $realFile    = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('real_cfg_', true) . '.php';
        $symlinkFile = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('symlink_cfg_', true) . '.php';

        file_put_contents($realFile, '<?php return ["env" => "prod"];');

        if (! @symlink($realFile, $symlinkFile)) {
            unlink($realFile);
            self::markTestSkipped('Symlinks cannot be created in this environment.');
        }

        try {
            $config = new PhpFileConfig($symlinkFile, persistent: true);
            $config->set('env', 'staging');

            self::assertTrue(is_link($symlinkFile));
            $reloadedReal = new PhpFileConfig($realFile);
            self::assertSame([
                'env' => 'staging',
            ], $reloadedReal->toArray());
        } finally {
            if (is_link($symlinkFile) || file_exists($symlinkFile)) {
                unlink($symlinkFile);
            }

            if (file_exists($realFile)) {
                unlink($realFile);
            }

            $realLock = \dirname($realFile) . '/.' . basename($realFile) . '.lock';
            if (file_exists($realLock)) {
                @unlink($realLock);
            }
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function relativeFileCases(): iterable
    {
        yield 'bare filename read' => ['settings.php', false];
        yield 'bare filename mutation' => ['settings.php', true];
        yield 'explicit relative mutation' => ['./settings.php', true];
        yield 'nested relative mutation' => ['nested/settings.php', true];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('relativeFileCases')]
    public function testRelativeFilesIgnoreCompetingIncludePathEntries(string $file, bool $persistent): void
    {
        mkdir($this->tempDirectory . '/cwd/nested', 0o777, true);
        mkdir($this->tempDirectory . '/include/nested', 0o777, true);

        $localFile = $this->tempDirectory . '/cwd/' . $file;
        $includedFile = $this->tempDirectory . '/include/' . $file;
        $localData = [
            'source' => 'cwd',
            'keep_local' => true,
        ];
        $includedData = [
            'source' => 'include_path',
        ];

        file_put_contents($localFile, '<?php return ' . var_export($localData, true) . ';');
        file_put_contents($includedFile, '<?php return ' . var_export($includedData, true) . ';');

        $workingDirectory = getcwd();
        $includePath = get_include_path();

        try {
            chdir($this->tempDirectory . '/cwd');
            set_include_path($this->tempDirectory . '/include');

            $config = new PhpFileConfig($file, $persistent);
            if ($persistent) {
                $config->set('saved', true);
                $localData['saved'] = true;
            }

            self::assertSame($localData, $config->toArray());
            self::assertSame($localData, require $localFile);
            self::assertSame($includedData, require $includedFile);
        } finally {
            set_include_path($includePath);
            chdir($workingDirectory);
        }
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function workingDirectoryChanges(): iterable
    {
        yield 'mutation first without competing file' => [false, false];
        yield 'mutation first with competing file' => [false, true];
        yield 'read first without competing file' => [true, false];
        yield 'read first with competing file' => [true, true];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('workingDirectoryChanges')]
    public function testPersistentMutationsKeepTheirTargetWhenLoadedCodeChangesCwd(
        bool $readFirst,
        bool $competingFile
    ): void {
        mkdir($this->tempDirectory . '/source', 0o777, true);
        mkdir($this->tempDirectory . '/other');

        $sourceFile = $this->tempDirectory . '/source/settings.php';
        file_put_contents(
            $sourceFile,
            '<?php chdir(' . var_export($this->tempDirectory . '/other', true) . '); return ["initial" => true];'
        );
        $otherFile = $this->tempDirectory . '/other/settings.php';
        $otherContent = '<?php return ["other" => true];';
        if ($competingFile) {
            file_put_contents($otherFile, $otherContent);
        }

        $workingDirectory = getcwd();

        try {
            chdir($this->tempDirectory . '/source');
            $config = new PhpFileConfig('settings.php', persistent: true);
            if ($readFirst) {
                self::assertTrue($config->get('initial'));
            }

            $config->set('saved', true);
            $config->set('again', true);
            $config->remove('initial');

            self::assertSame([
                'saved' => true,
                'again' => true,
            ], require $sourceFile);
            if ($competingFile) {
                self::assertSame($otherContent, file_get_contents($otherFile));
            } else {
                self::assertFileDoesNotExist($otherFile);
            }
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testRelativeSymlinkCanBeRetargetedAfterItsFirstRead(): void
    {
        mkdir($this->tempDirectory . '/source', 0o777, true);
        mkdir($this->tempDirectory . '/other');
        $first = $this->tempDirectory . '/source/first.php';
        $second = $this->tempDirectory . '/source/second.php';
        $link = $this->tempDirectory . '/source/settings.php';
        file_put_contents($first, '<?php return ["first" => true];');
        file_put_contents($second, '<?php return ["second" => true];');
        $this->createSymlink('first.php', $link);
        $workingDirectory = getcwd();

        try {
            chdir($this->tempDirectory . '/source');
            $config = new PhpFileConfig('settings.php', persistent: true);
            self::assertTrue($config->get('first'));

            chdir($this->tempDirectory . '/other');
            unlink($link);
            $this->createSymlink('second.php', $link);
            $config->set('saved', true);

            self::assertSame([
                'first' => true,
            ], require $first);
            self::assertSame([
                'second' => true,
                'saved' => true,
            ], require $second);
            self::assertSame('second.php', readlink($link));
            self::assertFileDoesNotExist($this->tempDirectory . '/other/settings.php');
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function externallyRetargetedDirectories(): iterable
    {
        yield 'direct parent' => [''];
        yield 'ancestor directory' => ['/nested'];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('externallyRetargetedDirectories')]
    public function testPersistentMutationsFollowDirectorySymlinksRetargetedByAnotherProcess(string $suffix): void
    {
        if (! \function_exists('proc_open')) {
            self::markTestSkipped('Subprocess support is required to retain the parent realpath cache.');
        }

        mkdir($this->tempDirectory . '/first' . $suffix, 0o777, true);
        mkdir($this->tempDirectory . '/second' . $suffix, 0o777, true);
        $first = $this->tempDirectory . '/first' . $suffix . '/settings.php';
        $second = $this->tempDirectory . '/second' . $suffix . '/settings.php';
        $link = $this->tempDirectory . '/current';
        file_put_contents($first, '<?php return ["first" => true];');
        file_put_contents($second, '<?php return ["second" => true];');
        $this->createSymlink($this->tempDirectory . '/first', $link);

        $config = new PhpFileConfig($link . $suffix . '/settings.php', persistent: true);
        self::assertTrue($config->get('first'));
        $this->runPhpSubprocess([__DIR__ . '/Stub/retarget-symlink.php', $link, $this->tempDirectory . '/second']);
        $config->set('saved', true);
        $config->remove('second');

        self::assertSame([
            'first' => true,
        ], require $first);
        self::assertSame([
            'saved' => true,
        ], require $second);
        self::assertFileDoesNotExist(\dirname($first) . '/.settings.php.lock');
        self::assertFileExists(\dirname($second) . '/.settings.php.lock');
    }

    /**
     * @return void
     */
    #[Test]
    public function testMissingRelativeFileKeepsItsLocationAfterReadingDefaults(): void
    {
        mkdir($this->tempDirectory . '/source', 0o777, true);
        mkdir($this->tempDirectory . '/other');
        $workingDirectory = getcwd();

        try {
            chdir($this->tempDirectory . '/source');
            $config = new PhpFileConfig('nested/settings.php', persistent: true, defaultConfig: [
                'initial' => true,
            ]);
            self::assertTrue($config->get('initial'));

            chdir($this->tempDirectory . '/other');
            $config->set('saved', true);
            $config->remove('initial');

            self::assertFileExists($this->tempDirectory . '/source/nested/settings.php');
            self::assertSame([
                'saved' => true,
            ], require $this->tempDirectory . '/source/nested/settings.php');
            self::assertDirectoryDoesNotExist($this->tempDirectory . '/other/nested');
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @return iterable<string, array{bool, int}>
     */
    public static function danglingSymlinkChains(): iterable
    {
        yield 'single relative link' => [false, 1];
        yield 'relative chain' => [false, 3];
        yield 'absolute chain' => [true, 3];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('danglingSymlinkChains')]
    public function testPersistentMutationPreservesEveryDanglingSymlink(bool $absolute, int $length): void
    {
        mkdir($this->tempDirectory);
        $targets = [];
        for ($index = 0; $index < $length; ++$index) {
            $target = $index + 1 < $length ? 'link' . ($index + 1) . '.php' : 'nested/target.php';
            $targets[] = $absolute ? $this->tempDirectory . '/' . $target : $target;
            $this->createSymlink($targets[$index], $this->tempDirectory . '/link' . $index . '.php');
        }

        $targetFile = $this->tempDirectory . '/nested/target.php';
        $config = new PhpFileConfig($this->tempDirectory . '/link0.php', persistent: true, defaultConfig: [
            'initial' => true,
        ]);
        $config->set('saved', true);

        foreach ($targets as $index => $target) {
            self::assertTrue(is_link($this->tempDirectory . '/link' . $index . '.php'));
            self::assertSame($target, readlink($this->tempDirectory . '/link' . $index . '.php'));
            self::assertFileDoesNotExist($this->tempDirectory . '/.link' . $index . '.php.lock');
        }

        self::assertFileExists($this->tempDirectory . '/nested/.target.php.lock');
        self::assertSame([
            'initial' => true,
            'saved' => true,
        ], require $targetFile);

        $secondInstance = new PhpFileConfig($targetFile, persistent: true);
        $secondInstance->set('other', true);

        $config->remove('initial');
        self::assertSame([
            'saved' => true,
            'other' => true,
        ], require $targetFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testCyclicSymlinkChainFailsBeforeAlteringAnyLink(): void
    {
        mkdir($this->tempDirectory);
        $first = $this->tempDirectory . '/first.php';
        $second = $this->tempDirectory . '/second.php';
        $this->createSymlink('second.php', $first);
        $this->createSymlink('./first.php', $second);

        try {
            (new PhpFileConfig($first, persistent: true, defaultConfig: []))->set('saved', true);
            self::fail('Cyclic links must be rejected before persistence.');
        } catch (InvalidArgumentException) {
            self::assertTrue(is_link($first));
            self::assertTrue(is_link($second));
            self::assertSame('second.php', readlink($first));
            self::assertSame('./first.php', readlink($second));
            self::assertCount(2, iterator_to_array(new FilesystemIterator($this->tempDirectory)));
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testDanglingSymlinkChainRemainsIntactWhenItsTargetCannotBeWritten(): void
    {
        mkdir($this->tempDirectory);
        $first = $this->tempDirectory . '/first.php';
        $second = $this->tempDirectory . '/second.php';
        $this->createSymlink('second.php', $first);
        $this->createSymlink('blocked/target.php', $second);
        file_put_contents($this->tempDirectory . '/blocked', 'not a directory');

        try {
            (new PhpFileConfig($first, persistent: true, defaultConfig: []))->set('saved', true);
            self::fail('An unresolvable target must be rejected before persistence.');
        } catch (InvalidArgumentException) {
            self::assertTrue(is_link($first));
            self::assertTrue(is_link($second));
            self::assertSame('second.php', readlink($first));
            self::assertSame('blocked/target.php', readlink($second));
            self::assertSame('not a directory', file_get_contents($this->tempDirectory . '/blocked'));
            self::assertCount(3, iterator_to_array(new FilesystemIterator($this->tempDirectory)));
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testPersistentMutationsPreserveAnExistingPermittedGroup(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR || ! \function_exists('posix_getgroups')) {
            self::markTestSkipped('POSIX supplementary groups are required for this regression.');
        }

        file_put_contents($this->tempFile, '<?php return ["initial" => true];');
        $originalGroup = filegroup($this->tempFile);
        $group = null;
        foreach (posix_getgroups() as $candidate) {
            if ($candidate !== $originalGroup && @chgrp($this->tempFile, $candidate)) {
                $group = $candidate;

                break;
            }
        }

        if (null === $group) {
            self::markTestSkipped('No different permitted supplementary group is available.');
        }

        chmod($this->tempFile, 0o640);
        clearstatcache(true, $this->tempFile);
        self::assertSame($group, filegroup($this->tempFile));

        $config = new PhpFileConfig($this->tempFile, persistent: true);
        $config->set('saved', true);
        clearstatcache(true, $this->tempFile);
        self::assertSame($group, filegroup($this->tempFile));
        self::assertSame(0o640, fileperms($this->tempFile) & 0o777);

        $config->remove('initial');
        clearstatcache(true, $this->tempFile);
        self::assertSame($group, filegroup($this->tempFile));
        self::assertSame(0o640, fileperms($this->tempFile) & 0o777);
        self::assertSame([
            'saved' => true,
        ], require $this->tempFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testUnpreservableGroupLeavesTheOriginalFileUntouched(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR || ! \function_exists('posix_getgroups') || ! is_writable('/tmp')) {
            self::markTestSkipped('A writable POSIX temporary directory is required.');
        }

        $inheritedFile = tempnam('/tmp', 'config_group_');
        $group = filegroup($inheritedFile);
        if (\in_array($group, posix_getgroups(), true)) {
            unlink($inheritedFile);
            self::markTestSkipped('Temporary files do not inherit a group outside the current supplementary groups.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        if (! @rename($inheritedFile, $file)) {
            unlink($inheritedFile);
            self::markTestSkipped('The inherited-group fixture cannot be moved to the configuration directory.');
        }

        $probe = tempnam($this->tempDirectory, 'group_');
        $canPreserveGroup = @chgrp($probe, $group);
        unlink($probe);
        if ($canPreserveGroup) {
            self::markTestSkipped('The current process can assign the inherited group.');
        }

        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        chmod($file, 0o640);

        try {
            (new PhpFileConfig($file, persistent: true))->set('saved', true);
            self::fail('An atomic replacement must not discard an unpreservable group.');
        } catch (InvalidArgumentException) {
            clearstatcache(true, $file);
            self::assertSame($content, file_get_contents($file));
            self::assertSame($group, filegroup($file));
            self::assertSame(0o640, fileperms($file) & 0o777);
            self::assertSame([], glob($this->tempDirectory . '/cfg_*'));
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function disabledMetadataFunctions(): iterable
    {
        yield 'cache refresh disabled during set' => ['clearstatcache', 'set'];
        yield 'cache refresh disabled during remove' => ['clearstatcache', 'remove'];
        yield 'stat disabled during set' => ['stat', 'set'];
        yield 'stat disabled during remove' => ['stat', 'remove'];
        yield 'filegroup disabled during set' => ['filegroup', 'set'];
        yield 'filegroup disabled during remove' => ['filegroup', 'remove'];
        yield 'chgrp disabled during set' => ['chgrp', 'set'];
        yield 'chgrp disabled during remove' => ['chgrp', 'remove'];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('disabledMetadataFunctions')]
    public function testUnavailableMetadataFunctionsRejectReplacementWithoutChangingTheFile(
        string $function,
        string $operation
    ): void {
        if ('\\' === \DIRECTORY_SEPARATOR || ! \function_exists('proc_open')) {
            self::markTestSkipped('POSIX metadata and subprocess support are required.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        $group = filegroup($file);

        if ('chgrp' === $function) {
            if (! \function_exists('posix_getgroups')) {
                self::markTestSkipped('Supplementary groups are required to exercise a group change.');
            }

            $group = null;
            $creationGroup = filegroup($file);
            foreach (posix_getgroups() as $candidate) {
                if ($candidate !== $creationGroup && @chgrp($file, $candidate)) {
                    $group = $candidate;

                    break;
                }
            }

            if (null === $group) {
                self::markTestSkipped('No different permitted supplementary group is available.');
            }
        }

        chmod($file, 0o640);
        $result = $this->runWithDisabledMetadataFunctions($file, $operation, $function);
        self::assertFalse($result['function_available']);
        self::assertSame(
            InvalidArgumentException::class,
            $result['exception'],
            $result['message'] ?? 'No exception was thrown.'
        );
        clearstatcache(true, $file);
        self::assertSame($content, file_get_contents($file));
        self::assertSame($group, filegroup($file));
        self::assertSame(0o640, fileperms($file) & 0o777);
        self::assertSame([], glob($this->tempDirectory . '/cfg_*'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function unusedMetadataFunctions(): iterable
    {
        yield 'new file without metadata functions' => ['stat,filegroup,chgrp', false];
        yield 'existing group already matches without chgrp' => ['chgrp', true];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('unusedMetadataFunctions')]
    public function testUnusedMetadataFunctionsDoNotBlockPersistence(string $functions, bool $existing): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR || ! \function_exists('proc_open')) {
            self::markTestSkipped('POSIX metadata and subprocess support are required.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        if ($existing) {
            file_put_contents($file, '<?php return ["initial" => true];');
        }

        $result = $this->runWithDisabledMetadataFunctions($file, 'set', $functions);
        self::assertFalse($result['function_available']);
        self::assertNull($result['exception'], $result['message'] ?? '');
        self::assertTrue((require $file)['saved']);
        if ($existing) {
            self::assertTrue((require $file)['initial']);
        }

        self::assertSame([], glob($this->tempDirectory . '/cfg_*'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testNewPersistentFileUsesUmaskAndDefaultCreationGroup(): void
    {
        mkdir($this->tempDirectory);
        $probe = tempnam($this->tempDirectory, 'group_');
        $defaultGroup = filegroup($probe);
        unlink($probe);
        $mask = umask(0o027);

        try {
            $file = $this->tempDirectory . '/new.php';
            (new PhpFileConfig($file, persistent: true, defaultConfig: []))->set('saved', true);

            self::assertSame([
                'saved' => true,
            ], require $file);
            if ('\\' !== \DIRECTORY_SEPARATOR) {
                clearstatcache(true, $file);
                self::assertSame(0o640, fileperms($file) & 0o777);
                self::assertSame($defaultGroup, filegroup($file));
            }
        } finally {
            umask($mask);
        }
    }

    /**
     * @param string $target
     * @param string $link
     *
     * @return void
     */
    private function createSymlink(string $target, string $link): void
    {
        if (! @symlink($target, $link)) {
            self::markTestSkipped('Symlinks cannot be created in this environment.');
        }
    }

    /**
     * @param string $file
     * @param string $operation
     * @param string $functions
     *
     * @return array{exception: string|null, function_available: bool, message?: string}
     */
    private function runWithDisabledMetadataFunctions(string $file, string $operation, string $functions): array
    {
        $autoload = \dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $output = $this->runPhpSubprocess([
            '-d',
            'disable_functions=' . $functions,
            __DIR__ . '/Stub/disabled-metadata-functions.php',
            $autoload,
            $file,
            $operation,
            explode(',', $functions)[0],
        ]);

        return json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $arguments
     *
     * @return string
     */
    private function runPhpSubprocess(array $arguments): string
    {
        $process = proc_open([\PHP_BINARY, '-n', ...$arguments], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);

        return $output;
    }

    /**
     * @return void
     */
    #[Test]
    public function testWindowsDriveRelativeExistingFileUsesNativeResolution(): void
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Windows drive-relative paths require Windows.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        file_put_contents($file, '<?php return ["initial" => true];');
        $workingDirectory = getcwd();

        try {
            chdir($this->tempDirectory);
            $driveRelative = substr(getcwd(), 0, 2) . 'settings.php';
            $config = new PhpFileConfig($driveRelative, persistent: true);
            $config->set('saved', true);

            self::assertSame([
                'initial' => true,
                'saved' => true,
            ], require $file);
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testUnresolvedWindowsDriveRelativePathFailsBeforeCreatingFiles(): void
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Windows drive-relative paths require Windows.');
        }

        mkdir($this->tempDirectory);
        $workingDirectory = getcwd();

        try {
            chdir($this->tempDirectory);
            $driveRelative = substr(getcwd(), 0, 2) . 'missing.php';

            try {
                (new PhpFileConfig($driveRelative, persistent: true, defaultConfig: []))->set('saved', true);
                self::fail('An unresolved drive-relative path must not create files.');
            } catch (InvalidArgumentException) {
                self::assertCount(0, iterator_to_array(new FilesystemIterator($this->tempDirectory)));
            }
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    private function removeDirectory(string $directory): void
    {
        foreach (new FilesystemIterator($directory) as $file) {
            if ('\\' === \DIRECTORY_SEPARATOR && $file->isLink()) {
                if (! @rmdir($file->getPathname())) {
                    unlink($file->getPathname());
                }
            } elseif ($file->isDir() && ! $file->isLink()) {
                $this->removeDirectory($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
