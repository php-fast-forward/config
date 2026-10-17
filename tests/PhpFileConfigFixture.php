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
use FilesystemIterator;
use ReflectionClass;

/**
 * @internal
 */
trait PhpFileConfigFixture
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
     * @param string|null $registeredStream
     *
     * @return array{exception: string|null, function_available: bool, message?: string, value?: mixed}
     */
    private function runWithDisabledMetadataFunctions(
        string $file,
        string $operation,
        string $functions,
        ?string $registeredStream = null
    ): array {
        $autoload = \dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $arguments = [
            '-d',
            'disable_functions=' . $functions,
            __DIR__ . '/Stub/disabled-metadata-functions.php',
            $autoload,
            $file,
            $operation,
            explode(',', $functions)[0],
        ];
        if (null !== $registeredStream) {
            $arguments[] = $registeredStream;
        }

        $output = $this->runPhpSubprocess($arguments);

        return json_decode((string) $output, true, 512, \JSON_THROW_ON_ERROR);
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
