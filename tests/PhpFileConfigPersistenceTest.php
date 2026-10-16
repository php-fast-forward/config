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
final class PhpFileConfigPersistenceTest extends TestCase
{
    use PhpFileConfigFixture;

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
        yield 'realpath disabled during set' => ['realpath', 'set'];
        yield 'realpath disabled during remove' => ['realpath', 'remove'];
        yield 'is_link disabled during set' => ['is_link', 'set'];
        yield 'is_link disabled during remove' => ['is_link', 'remove'];
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
        yield 'absolute regular file without path helpers' => ['getcwd,readlink', true];
        yield 'absolute regular file without stream enumeration' => ['stream_get_wrappers', true];
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
}
