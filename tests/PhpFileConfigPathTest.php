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
final class PhpFileConfigPathTest extends TestCase
{
    use PhpFileConfigFixture;

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
     * @return iterable<string, array{string, string, string}>
     */
    public static function trailingFileSeparators(): iterable
    {
        $separators = '\\' === \DIRECTORY_SEPARATOR ? ['/', '\\'] : ['/'];
        foreach ($separators as $separator) {
            foreach (['regular file', 'link input', 'link target'] as $kind) {
                foreach (['get', 'get defaults', 'set', 'remove'] as $operation) {
                    yield $kind . ' ' . $operation . ' with ' . $separator => [$kind, $operation, $separator];
                }
            }
        }
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('trailingFileSeparators')]
    public function testTrailingSeparatorsRetainNativeFileSemantics(
        string $kind,
        string $operation,
        string $separator
    ): void {
        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $link = $this->tempDirectory . '/link.php';
        $input = $file . $separator;
        $target = null;
        if ('link target' === $kind) {
            mkdir($file);
            $this->createSymlink($file . $separator, $link);
            $target = readlink($link);
            rmdir($file);
            $input = $link;
        }

        $content = '<?php return ["initial" => true, "remove" => true];';
        file_put_contents($file, $content);
        if ('link input' === $kind) {
            $this->createSymlink($file, $link);
            $target = readlink($link);
            $input = $link . $separator;
        }

        clearstatcache(true);
        $nativeExists = file_exists($input);
        self::assertFalse(is_file($input));
        $default = 'get' === $operation ? null : [
            'fallback' => true,
        ];
        $config = new PhpFileConfig($input, persistent: true, defaultConfig: $default);
        try {
            if ('get defaults' === $operation && ! $nativeExists) {
                self::assertSame($default, $config->toArray());
            } else {
                if ('get' === $operation || 'get defaults' === $operation) {
                    $config->get('initial');
                } elseif ('set' === $operation) {
                    $config->set('saved', true);
                } else {
                    $config->remove('remove');
                }

                self::fail('A file followed by a separator must not be read or replaced as that file.');
            }
        } catch (InvalidArgumentException) {
            self::assertTrue('get defaults' !== $operation || $nativeExists);
        }

        self::assertSame($content, file_get_contents($file));
        self::assertSame([], glob($this->tempDirectory . '/cfg_*'));
        if (null !== $target) {
            self::assertTrue(is_link($link));
            self::assertSame($target, readlink($link));
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testPosixTerminalBackslashRemainsAValidFilenameCharacter(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('A backslash is a path separator on Windows.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php\\';
        file_put_contents($file, '<?php return ["initial" => true];');
        $config = new PhpFileConfig($file, persistent: true);
        self::assertTrue($config->get('initial'));
        $config->set('saved', true);
        $config->remove('initial');

        self::assertSame([
            'saved' => true,
        ], require $file);
        self::assertFileDoesNotExist($this->tempDirectory . '/settings.php');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nulPaths(): iterable
    {
        foreach (['terminal', 'ancestor', 'terminal stream', 'ancestor stream'] as $component) {
            foreach (['get', 'defaults', 'set', 'remove'] as $operation) {
                yield $component . ' during ' . $operation => [$component, $operation];
            }
        }
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('nulPaths')]
    public function testNulPathsRetainMissingReadSemanticsAndRejectPersistentChanges(
        string $component,
        string $operation
    ): void {
        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        $input = str_starts_with($component, 'terminal')
            ? $file . "\0suffix"
            : $this->tempDirectory . "/missing/invalid\0/settings.php";
        if (str_ends_with($component, 'stream')) {
            $path = str_replace('\\', '/', $input);
            $input = 'file://' . (str_starts_with($path, '/') ? '' : '/') . $path;
        }

        self::assertFalse(file_exists($input));

        $config = new PhpFileConfig($input, persistent: true, defaultConfig: 'get' === $operation ? null : [
            'fallback' => true,
        ]);
        try {
            if ('defaults' === $operation) {
                self::assertSame([
                    'fallback' => true,
                ], $config->toArray());
            } elseif ('set' === $operation) {
                $config->set('saved', true);
                self::fail('A NUL path must not be persisted.');
            } elseif ('remove' === $operation) {
                $config->remove('fallback');
                self::fail('A NUL path must not be persisted.');
            } else {
                $config->get('initial');
                self::fail('A NUL path without defaults must not be read.');
            }
        } catch (InvalidArgumentException) {
            self::assertNotSame('defaults', $operation);
        }

        self::assertSame($content, file_get_contents($file));
        self::assertCount(1, iterator_to_array(new FilesystemIterator($this->tempDirectory)));
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
     * @return iterable<string, array{string, string}>
     */
    public static function unavailableRelativeOrLinkFunctions(): iterable
    {
        foreach (['get', 'set', 'remove'] as $operation) {
            yield 'relative input during ' . $operation => ['getcwd', $operation];
            yield 'symlink input during ' . $operation => ['readlink', $operation];
            yield 'stream input during ' . $operation => ['stream_get_wrappers', $operation];
            yield 'stream input without regex during ' . $operation => ['preg_match', $operation];
        }
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('unavailableRelativeOrLinkFunctions')]
    public function testUnavailableRequiredPathHelpersRejectOperationsWithoutChangingTheFile(
        string $function,
        string $operation
    ): void {
        if (! \function_exists('proc_open')) {
            self::markTestSkipped('Subprocess support is required to disable path helpers.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        $input = 'settings.php';
        if ('readlink' === $function) {
            $input = $this->tempDirectory . '/link.php';
            $this->createSymlink('settings.php', $input);
        } elseif ('stream_get_wrappers' === $function || 'preg_match' === $function) {
            $path = str_replace('\\', '/', $file);
            $input = 'file://' . (str_starts_with($path, '/') ? '' : '/') . $path;
        }

        $workingDirectory = getcwd();
        try {
            chdir($this->tempDirectory);
            $result = $this->runWithDisabledMetadataFunctions($input, $operation, $function);
            self::assertFalse($result['function_available']);
            self::assertSame(InvalidArgumentException::class, $result['exception'], $result['message'] ?? '');
            self::assertSame($content, file_get_contents($file));
            self::assertSame([], glob($this->tempDirectory . '/cfg_*'));
            self::assertFileDoesNotExist($this->tempDirectory . '/.settings.php.lock');
            if ('readlink' === $function) {
                self::assertSame('settings.php', readlink($input));
            }
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function readsWithoutPathFunctions(): iterable
    {
        yield 'absolute input without realpath' => ['realpath', false];
        yield 'relative input without realpath' => ['realpath', true];
        yield 'absolute input without is_link' => ['is_link', false];
        yield 'relative input without is_link' => ['is_link', true];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('readsWithoutPathFunctions')]
    public function testUnavailablePathFunctionsRejectReadsThroughTheLibraryException(
        string $function,
        bool $relative
    ): void {
        if (! \function_exists('proc_open')) {
            self::markTestSkipped('Subprocess support is required to disable path functions.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        $workingDirectory = getcwd();

        try {
            chdir($this->tempDirectory);
            $result = $this->runWithDisabledMetadataFunctions($relative ? 'settings.php' : $file, 'get', $function);
            self::assertFalse($result['function_available']);
            self::assertSame(InvalidArgumentException::class, $result['exception'], $result['message'] ?? '');
            self::assertSame($content, file_get_contents($file));
            self::assertCount(1, iterator_to_array(new FilesystemIterator($this->tempDirectory)));
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function filesystemOperationsWithoutRegex(): iterable
    {
        foreach ([false, true] as $relative) {
            foreach (['get', 'set', 'remove'] as $operation) {
                yield ($relative ? 'relative ' : 'absolute ') . $operation => [$relative, $operation];
            }
        }
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('filesystemOperationsWithoutRegex')]
    public function testPosixFilesystemPathsDoNotRequireRegex(bool $relative, string $operation): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR || ! \function_exists('proc_open')) {
            self::markTestSkipped('POSIX paths and subprocess support are required.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        $workingDirectory = getcwd();
        try {
            chdir($this->tempDirectory);
            $result = $this->runWithDisabledMetadataFunctions(
                $relative ? 'settings.php' : $file,
                $operation,
                'preg_match'
            );
            self::assertFalse($result['function_available']);
            self::assertNull($result['exception'], $result['message'] ?? '');
            $expected = match ($operation) {
                'set' => [
                    'initial' => true,
                    'saved' => true,
                ],
                'remove' => [],
                default => [
                    'initial' => true,
                ],
            };
            self::assertSame($expected, require $file);
            self::assertSame([], glob($this->tempDirectory . '/cfg_*'));
        } finally {
            chdir($workingDirectory);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unavailableComponentFunctions(): iterable
    {
        foreach (['dirname', 'basename'] as $function) {
            foreach (['file', 'missing', 'stream', 'terminal'] as $kind) {
                yield $kind . ' without ' . $function => [$function, $kind];
            }
        }
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('unavailableComponentFunctions')]
    public function testPathComponentsRequireHelpersOnlyDuringTraversal(string $function, string $kind): void
    {
        if (! \function_exists('proc_open')) {
            self::markTestSkipped('Subprocess support is required to disable path helpers.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        $path = str_replace('\\', '/', $file);
        $input = match ($kind) {
            'stream' => 'file://' . (str_starts_with($path, '/') ? '' : '/') . $path,
            'terminal' => $file . '/',
            'missing' => $this->tempDirectory . '/missing/settings.php',
            default => $file,
        };
        $vendor = \dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 2);
        $output = $this->runPhpSubprocess([
            '-d',
            'disable_functions=' . $function,
            __DIR__ . '/Stub/disabled-component-functions.php',
            $vendor,
            __DIR__ . '/../src',
            $input,
            $kind,
            $function,
        ]);
        $result = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($result['function_available']);
        if ('stream' === $kind || 'terminal' === $kind) {
            self::assertNull($result['exception'], $result['message'] ?? '');
            self::assertTrue($result['value']);
        } else {
            self::assertSame(InvalidArgumentException::class, $result['exception'], $result['message'] ?? '');
        }

        self::assertSame($content, file_get_contents($file));
        self::assertCount(1, iterator_to_array(new FilesystemIterator($this->tempDirectory)));
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
            if (false === realpath($driveRelative)) {
                try {
                    $config->set('saved', true);
                    self::fail('A drive-relative path that native PHP cannot resolve must be rejected.');
                } catch (InvalidArgumentException) {
                    self::assertSame([
                        'initial' => true,
                    ], require $file);
                    self::assertFileDoesNotExist($this->tempDirectory . '/.settings.php.lock');
                }

                return;
            }

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
}
