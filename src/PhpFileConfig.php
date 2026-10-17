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

namespace FastForward\Config;

use Brick\VarExporter\VarExporter;
use FastForward\Config\Exception\InvalidArgumentException;
use Throwable;

/**
 * Class PhpFileConfig.
 *
 * Provides a configuration source backed by a single PHP file returning an array.
 * This class MUST load the configuration from the specified PHP file.
 * It SHALL lazily initialize and retrieve configuration data upon invocation.
 * If configured as persistent, mutations made via `set` and `remove` SHALL be persisted back to the file.
 */
final class PhpFileConfig implements ConfigInterface
{
    use LazyLoadConfigTrait;

    private ?string $absoluteFile = null;

    /**
     * Constructs a PhpFileConfig instance.
     *
     * @param string $file the path to the PHP configuration file
     * @param bool $persistent whether changes should be saved back to the file
     * @param array<string, mixed>|ConfigInterface|null $defaultConfig optional default configuration when the file does not exist
     */
    public function __construct(
        private readonly string $file,
        private readonly bool $persistent = false,
        private readonly array|ConfigInterface|null $defaultConfig = null,
    ) {}

    /**
     * Invokes the configuration and returns the configuration data.
     *
     * If the file exists, it MUST return a valid array.
     * If the file does not exist but a default configuration is provided,
     * the default configuration SHALL be used.
     *
     * @return ConfigInterface a ConfigInterface implementation containing the configuration data
     *
     * @throws InvalidArgumentException if the file is unreadable or does not return an array
     */
    public function __invoke(): ConfigInterface
    {
        return $this->loadConfig();
    }

    /**
     * Sets configuration data.
     *
     * This method MUST update the configuration data in the file if the persistent flag is set to true.
     *
     * @param array<string, mixed>|ConfigInterface|string $key the configuration key or an array of key-value pairs to set
     * @param mixed $value the value to set for the specified key
     *
     * @return void
     *
     * @throws InvalidArgumentException if the key is invalid or file cannot be written
     */
    public function set(array|ConfigInterface|string $key, mixed $value = null): void
    {
        if (! $this->persistent) {
            $this->getConfig()
                ->set($key, $value);

            return;
        }

        $this->mutate(static function (ConfigInterface $config) use ($key, $value): void {
            $config->set($key, $value);
        });
    }

    /**
     * Removes a configuration key and its associated value.
     *
     * This method MUST update the configuration data in the file if the persistent flag is set to true.
     *
     * @param string $key the configuration key to remove
     *
     * @return void
     *
     * @throws InvalidArgumentException if the file cannot be written
     */
    public function remove(string $key): void
    {
        if (! $this->persistent) {
            $this->getConfig()
                ->remove($key);

            return;
        }

        $this->mutate(static function (ConfigInterface $config) use ($key): void {
            $config->remove($key);
        });
    }

    /**
     * Loads the configuration from the file or default configuration.
     *
     * @param string|null $targetFile resolved target shared with a persistent mutation
     *
     * @return ConfigInterface
     *
     * @throws InvalidArgumentException if the file is unreadable or does not return an array
     */
    private function loadConfig(?string $targetFile = null): ConfigInterface
    {
        $targetFile ??= $this->resolveTargetFile();

        if (! file_exists($targetFile)) {
            if (null !== $this->defaultConfig) {
                $data = $this->defaultConfig instanceof ConfigInterface
                    ? $this->defaultConfig->toArray()
                    : $this->defaultConfig;

                return new ArrayConfig($data);
            }

            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        if (! is_file($targetFile) || ! is_readable($targetFile)) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        $data = (static fn(string $file): mixed => require $file)($targetFile);

        if (! \is_array($data)) {
            throw InvalidArgumentException::forInvalidConfigFile($this->file);
        }

        return new ArrayConfig($data);
    }

    /**
     * Mutates the resolved target under an exclusive process lock.
     *
     * @param callable(ConfigInterface): void $operation
     */
    private function mutate(callable $operation): void
    {
        $targetFile = $this->resolveTargetFile();
        if ('' === $targetFile || str_contains($targetFile, "\0") || str_ends_with($targetFile, '/')
            || ('\\' === \DIRECTORY_SEPARATOR && str_ends_with($targetFile, '\\'))) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        $dir = $this->prepareWritableTarget($targetFile);
        $lockPath = $dir . '/.' . basename($targetFile) . '.lock';
        $lockFp   = @fopen($lockPath, 'c+');

        if (false === $lockFp) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        try {
            $this->mutateUnderLock($lockFp, $targetFile, $operation);
        } finally {
            @fclose($lockFp);
        }
    }

    /**
     * @param resource $lockFp
     * @param string $targetFile
     * @param callable(ConfigInterface): void $operation
     */
    private function mutateUnderLock($lockFp, string $targetFile, callable $operation): void
    {
        if (! @flock($lockFp, \LOCK_EX)) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        try {
            $config = $this->loadConfig($targetFile);
            $operation($config);
            $this->dump($config->toArray(), $targetFile);
            $this->config = $config;
        } finally {
            @flock($lockFp, \LOCK_UN);
        }
    }

    /**
     * @param string $targetFile
     *
     * @return string
     */
    private function prepareWritableTarget(string $targetFile): string
    {
        $dir = \dirname($targetFile);

        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0o777, true) && ! is_dir($dir)) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }
        } elseif (! is_writable($dir)) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        if (file_exists($targetFile) && (! is_file($targetFile) || ! is_writable($targetFile))) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        return $dir;
    }

    /**
     * Publishes configuration atomically after revalidating the locked target.
     *
     * @param array<array-key, mixed> $data
     * @param string $targetFile
     */
    private function dump(array $data, string $targetFile): void
    {
        $dir = $this->prepareWritableTarget($targetFile);
        $existingMetadata = $this->readExistingMetadata($targetFile);
        $content = $this->exportContent($data);
        $tmpFile = tempnam($dir, 'cfg_');
        if (false === $tmpFile) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        try {
            $bytesWritten = @file_put_contents($tmpFile, $content, \LOCK_EX);
            if ($bytesWritten !== \strlen($content)) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }

            $this->applyTemporaryMetadata($tmpFile, $existingMetadata);
            if (! @rename($tmpFile, $targetFile)) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }
        } finally {
            @unlink($tmpFile);
        }

        if (\function_exists('opcache_invalidate')) {
            @opcache_invalidate($targetFile, true);
        }
    }

    /**
     * @param string $targetFile
     *
     * @return array<array-key, int>|null
     */
    private function readExistingMetadata(string $targetFile): ?array
    {
        if (! file_exists($targetFile)) {
            return null;
        }

        if (! \function_exists('stat')) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        $metadata = @stat($targetFile);
        if (false === $metadata) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        return $metadata;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function exportContent(array $data): string
    {
        try {
            $exported = VarExporter::export($data);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }

        return "<?php\n\ndeclare(strict_types=1);\n\nreturn {$exported};\n";
    }

    /**
     * @param string $tmpFile
     * @param array<array-key, int>|null $existingMetadata
     */
    private function applyTemporaryMetadata(string $tmpFile, ?array $existingMetadata): void
    {
        if (null !== $existingMetadata && '\\' !== \DIRECTORY_SEPARATOR) {
            if (! \function_exists('filegroup')) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }

            $temporaryGroup = @filegroup($tmpFile);
            if (false === $temporaryGroup) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }

            if ($temporaryGroup !== $existingMetadata['gid']
                && (! \function_exists('chgrp') || ! @chgrp($tmpFile, $existingMetadata['gid']))) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }
        }

        if (! \function_exists('chmod') || (null === $existingMetadata && ! \function_exists('umask'))) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        $mode = null !== $existingMetadata ? ($existingMetadata['mode'] & 0o777) : (0o666 & ~umask());
        if (! @chmod($tmpFile, $mode)) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }
    }

    /**
     * Anchors the input location on first use and resolves its current symlink target.
     *
     * @return string
     */
    private function resolveTargetFile(): string
    {
        if ('' === $this->file || str_contains($this->file, "\0")) {
            return $this->file;
        }

        if (! \function_exists('realpath') || ! \function_exists('clearstatcache') || ! \function_exists('is_link')) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        clearstatcache(true);
        $file = $this->absoluteFile ??= $this->absolutePath($this->file);
        if ($this->isStreamPath($file)) {
            return $file;
        }

        if (file_exists($file) && ! is_file($file)) {
            return $file;
        }

        return $this->followSymlinks($file);
    }

    /**
     * @param string $file
     *
     * @return string
     */
    private function followSymlinks(string $file): string
    {
        $visited = [];

        for ($depth = 0; $depth < 64; ++$depth) {
            if (str_ends_with($file, '/') || ('\\' === \DIRECTORY_SEPARATOR && str_ends_with($file, '\\'))) {
                return $file;
            }

            if (! \function_exists('dirname') || ! \function_exists('basename')) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            $component = $this->findSymlinkComponent($file);
            $link = $component['path'];
            $suffix = $component['suffix'];
            if (! $component['linked']) {
                $file = $link . $suffix;
                $resolved = realpath($file);

                return false !== $resolved ? $resolved : $file;
            }

            $file = $link . $suffix;
            if (isset($visited[$file]) || ! \function_exists('readlink')) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            $visited[$file] = true;
            $target = @readlink($link);
            if (false === $target) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            $file = $this->absolutePath($target, \dirname($link)) . $suffix;
        }

        throw InvalidArgumentException::forUnreadableFile($this->file);
    }

    /**
     * @param string $file
     *
     * @return array{path: string, suffix: string, linked: bool}
     */
    private function findSymlinkComponent(string $file): array
    {
        $link = $file;
        $suffix = '';
        while (true) {
            $directory = realpath(\dirname($link));
            if (false !== $directory) {
                $link = $directory . \DIRECTORY_SEPARATOR . basename($link);
            }

            if (is_link($link)) {
                return [
                    'path' => $link,
                    'suffix' => $suffix,
                    'linked' => true,
                ];
            }

            $parent = \dirname($link);
            if (false !== $directory || $parent === $link) {
                return [
                    'path' => $link,
                    'suffix' => $suffix,
                    'linked' => false,
                ];
            }

            $suffix = \DIRECTORY_SEPARATOR . basename($link) . $suffix;
            $link = $parent;
        }
    }

    /**
     * Recognizes the registered protocols selected by PHP's native stream lookup.
     *
     * @param string $path
     *
     * @return bool
     */
    private function isStreamPath(string $path): bool
    {
        if (! str_contains($path, '://')) {
            return false;
        }

        if (! \function_exists('preg_match')) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        if (1 !== preg_match('~^([a-zA-Z0-9+.-]{2,})://~', $path, $matches)) {
            return false;
        }

        if (! \function_exists('stream_get_wrappers')) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        $wrappers = stream_get_wrappers();

        if (\in_array($matches[1], $wrappers, true)) {
            return true;
        }

        if (! \function_exists('strtolower')) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        return \in_array(strtolower($matches[1]), $wrappers, true);
    }

    /**
     * Resolves relative paths against their containing directory without using include_path.
     *
     * @param string $path the file or symlink target path
     * @param string|null $directory the directory containing a relative symlink target
     *
     * @return string
     */
    private function absolutePath(string $path, ?string $directory = null): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR && 1 === strspn($path, '\\/')) {
            return $this->anchorWindowsRootedPath($path, $directory);
        }

        if (str_starts_with($path, '/') || (null === $directory && $this->isStreamPath($path))
            || ('\\' === \DIRECTORY_SEPARATOR && str_starts_with($path, '\\'))) {
            return $path;
        }

        if ('\\' === \DIRECTORY_SEPARATOR) {
            if (! \function_exists('preg_match')) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            if (1 === preg_match('~^[a-zA-Z]:[\\\\/]~', $path)) {
                return $path;
            }

            if (1 === preg_match('~^[a-zA-Z]:~', $path)) {
                return $this->anchorWindowsDriveRelativePath($path);
            }
        }

        $directory ??= \function_exists('getcwd') ? getcwd() : false;
        if (false === $directory) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        return $directory . \DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param string $path
     * @param string|null $directory
     *
     * @return string
     */
    private function anchorWindowsRootedPath(string $path, ?string $directory): string
    {
        $directory ??= \function_exists('getcwd') ? getcwd() : false;
        if (false === $directory || ! \function_exists('preg_match') || 1 !== preg_match(
            '~^(?:[a-zA-Z]:|[\\\\/]{2}(?:[?.][\\\\/]UNC[\\\\/])?[^\\\\/]+[\\\\/][^\\\\/]+)~i',
            $directory,
            $root
        )) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        return $root[0] . $path;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function anchorWindowsDriveRelativePath(string $path): string
    {
        if (false === realpath($path)) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        $drive = substr($path, 0, 2);
        $directory = \function_exists('getcwd') ? getcwd() : false;
        if (false === $directory || 0 !== strncasecmp($drive, $directory, 2)) {
            $directory = realpath($drive . '.');
        }

        if (false === $directory) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        return rtrim($directory, '\\/') . \DIRECTORY_SEPARATOR . substr($path, 2);
    }
}
