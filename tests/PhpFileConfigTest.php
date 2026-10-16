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
     * @return void
     */
    #[Test]
    public function testPersistentMutationKeepsItsResolvedTargetWhenLoadedCodeChangesCwd(): void
    {
        mkdir($this->tempDirectory . '/source', 0o777, true);
        mkdir($this->tempDirectory . '/other');

        $sourceFile = $this->tempDirectory . '/source/settings.php';
        file_put_contents(
            $sourceFile,
            '<?php chdir(' . var_export($this->tempDirectory . '/other', true) . '); return ["initial" => true];'
        );
        $workingDirectory = getcwd();

        try {
            chdir($this->tempDirectory . '/source');
            $config = new PhpFileConfig('settings.php', persistent: true);
            $config->set('saved', true);

            self::assertSame([
                'initial' => true,
                'saved' => true,
            ], require $sourceFile);
            self::assertFileDoesNotExist($this->tempDirectory . '/other/settings.php');
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
