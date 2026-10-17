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
final class PhpFileConfigSymlinkTest extends TestCase
{
    use PhpFileConfigFixture;

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
     * @return iterable<string, array{bool}>
     */
    public static function literalUriSymlinkTargets(): iterable
    {
        yield 'existing literal target' => [true];
        yield 'dangling literal target' => [false];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('literalUriSymlinkTargets')]
    public function testPosixSymlinkTargetsContainingUriSyntaxRemainRelativePaths(bool $existing): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('POSIX permits colon characters in relative symlink targets.');
        }

        mkdir($this->tempDirectory . '/source', 0o777, true);
        $external = $this->tempDirectory . '/external.php';
        $externalContent = '<?php return ["external" => true];';
        file_put_contents($external, $externalContent);
        $link = $this->tempDirectory . '/source/settings.php';
        $literal = 'file://' . $external;
        $target = \dirname($link) . '/' . $literal;
        if ($existing) {
            mkdir(\dirname($target), 0o777, true);
            file_put_contents($target, '<?php return ["initial" => true];');
        }

        $this->createSymlink($literal, $link);
        if ($existing) {
            self::assertSame([
                'initial' => true,
            ], require $link);
        }

        $config = new PhpFileConfig($link, persistent: true, defaultConfig: [
            'initial' => true,
        ]);
        self::assertTrue($config->get('initial'));
        $config->set('saved', true);
        $config->remove('initial');

        self::assertSame([
            'saved' => true,
        ], require $target);
        self::assertSame($externalContent, file_get_contents($external));
        self::assertSame($literal, readlink($link));
        self::assertFileExists(\dirname($target) . '/.external.php.lock');
        self::assertFileDoesNotExist($this->tempDirectory . '/.external.php.lock');
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
     * @return iterable<string, array{bool, int, string}>
     */
    public static function danglingDirectorySymlinkChains(): iterable
    {
        yield 'single relative parent link' => [false, 1, ''];
        yield 'relative ancestor chain' => [false, 3, '/nested'];
        yield 'absolute ancestor chain' => [true, 3, '/nested'];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('danglingDirectorySymlinkChains')]
    public function testPersistentMutationCreatesTargetsThroughDanglingDirectorySymlinks(
        bool $absolute,
        int $length,
        string $suffix
    ): void {
        mkdir($this->tempDirectory . '/missing', 0o777, true);
        $targets = [];
        for ($index = $length - 1; $index >= 0; --$index) {
            $target = $index + 1 < $length ? 'link' . ($index + 1) : 'missing';
            $link = $this->tempDirectory . '/link' . $index;
            $this->createSymlink($absolute ? $this->tempDirectory . '/' . $target : $target, $link);
            $targets[$index] = readlink($link);
        }

        rmdir($this->tempDirectory . '/missing');
        $targetFile = $this->tempDirectory . '/missing' . $suffix . '/settings.php';
        $config = new PhpFileConfig($this->tempDirectory . '/link0' . $suffix . '/settings.php', persistent: true, defaultConfig: [
            'initial' => true,
        ]);
        self::assertTrue($config->get('initial'));
        self::assertDirectoryDoesNotExist($this->tempDirectory . '/missing');
        $config->set('saved', true);
        (new PhpFileConfig($targetFile, persistent: true))->set('other', true);
        $config->remove('initial');

        self::assertSame([
            'saved' => true,
            'other' => true,
        ], require $targetFile);
        self::assertFileExists(\dirname($targetFile) . '/.settings.php.lock');
        foreach ($targets as $index => $target) {
            $link = $this->tempDirectory . '/link' . $index;
            self::assertTrue(is_link($link));
            self::assertSame($target, readlink($link));
            self::assertFileDoesNotExist($this->tempDirectory . '/.link' . $index . '.lock');
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testRepeatedDirectoryAliasDoesNotBecomeASymlinkCycle(): void
    {
        mkdir($this->tempDirectory);
        $link = $this->tempDirectory . '/alias';
        $this->createSymlink('.', $link);
        $target = readlink($link);

        (new PhpFileConfig($link . '/alias/missing/settings.php', persistent: true, defaultConfig: []))
            ->set('saved', true);

        self::assertSame([
            'saved' => true,
        ], require $this->tempDirectory . '/missing/settings.php');
        self::assertTrue(is_link($link));
        self::assertSame($target, readlink($link));
    }

    /**
     * @return void
     */
    #[Test]
    public function testParentPathSegmentsKeepNativeDirectorySymlinkSemantics(): void
    {
        mkdir($this->tempDirectory . '/deep/subdir', 0o777, true);
        $link = $this->tempDirectory . '/alias';
        $this->createSymlink($this->tempDirectory . '/deep/subdir', $link);
        $target = readlink($link);
        $parent = realpath($link . '/..');
        self::assertNotFalse($parent);

        (new PhpFileConfig($link . '/../missing/settings.php', persistent: true, defaultConfig: []))
            ->set('saved', true);

        self::assertSame([
            'saved' => true,
        ], require $parent . '/missing/settings.php');
        $unexpected = $parent === realpath($this->tempDirectory)
            ? $this->tempDirectory . '/deep/missing'
            : $this->tempDirectory . '/missing';
        self::assertDirectoryDoesNotExist($unexpected);
        self::assertTrue(is_link($link));
        self::assertSame($target, readlink($link));
    }

    /**
     * @return void
     */
    #[Test]
    public function testCyclicDirectorySymlinkChainFailsBeforeCreatingAnyFile(): void
    {
        mkdir($this->tempDirectory);
        $first = $this->tempDirectory . '/first';
        $second = $this->tempDirectory . '/second';
        $this->createSymlink('second', $first);
        $this->createSymlink('first', $second);

        try {
            (new PhpFileConfig($first . '/settings.php', persistent: true, defaultConfig: []))->set('saved', true);
            self::fail('Cyclic directory links must be rejected before persistence.');
        } catch (InvalidArgumentException) {
            self::assertTrue(is_link($first));
            self::assertTrue(is_link($second));
            self::assertSame('second', readlink($first));
            self::assertSame('first', readlink($second));
            self::assertCount(2, iterator_to_array(new FilesystemIterator($this->tempDirectory)));
        }
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
}
