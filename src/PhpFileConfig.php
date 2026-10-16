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
     * Executes a persistent mutation under an exclusive process lock.
     *
     * @param callable(ConfigInterface): void $operation
     *
     * @return void
     *
     * @throws InvalidArgumentException if the lock or file cannot be written
     */
    private function mutate(callable $operation): void
    {
        $targetFile = $this->resolveTargetFile();
        $dir        = \dirname($targetFile);

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

        $lockPath = $dir . '/.' . basename($targetFile) . '.lock';
        $lockFp   = @fopen($lockPath, 'c+');

        if (false === $lockFp) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        try {
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
        } finally {
            @fclose($lockFp);
        }
    }

    /**
     * Dumps the configuration array to the PHP file.
     *
     * @param array<array-key, mixed> $data the configuration data to write
     * @param string|null $targetFile optional target file override (e.g. resolved symlink target)
     *
     * @return void
     *
     * @throws InvalidArgumentException if the file or directory is not writable, or cannot be exported
     */
    private function dump(array $data, ?string $targetFile = null): void
    {
        $targetFile ??= $this->resolveTargetFile();
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

        $existingMetadata = null;
        if (file_exists($targetFile)) {
            if (! \function_exists('stat')) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }

            $existingMetadata = @stat($targetFile);
            if (false === $existingMetadata) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }
        }

        try {
            $exported = VarExporter::export($data);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$exported};\n";

        $tmpFile = tempnam($dir, 'cfg_');
        if (false === $tmpFile) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        try {
            $bytesWritten = @file_put_contents($tmpFile, $content, \LOCK_EX);
            if ($bytesWritten !== \strlen($content)) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }

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

            $mode = null !== $existingMetadata ? ($existingMetadata['mode'] & 0o777) : (0o666 & ~umask());
            if (! @chmod($tmpFile, $mode) || ! @rename($tmpFile, $targetFile)) {
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
     * Anchors the input location on first use and resolves its current symlink target.
     *
     * @return string
     */
    private function resolveTargetFile(): string
    {
        if (! \function_exists('clearstatcache')) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        clearstatcache(true);
        $file = $this->absoluteFile ??= $this->absolutePath($this->file);
        $visited = [];

        for ($depth = 0; $depth < 64; ++$depth) {
            $directory = realpath(\dirname($file));
            if (false !== $directory) {
                $file = $directory . \DIRECTORY_SEPARATOR . basename($file);
            }

            if (! is_link($file)) {
                $resolved = realpath($file);

                return false !== $resolved ? $resolved : $file;
            }

            if (isset($visited[$file])) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            $visited[$file] = true;
            $target = @readlink($file);
            if (false === $target) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            $file = $this->absolutePath($target, \dirname($file));
        }

        throw InvalidArgumentException::forUnreadableFile($this->file);
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
            $directory ??= getcwd();
            if (false === $directory || 1 !== preg_match(
                '~^(?:[a-zA-Z]:|[\\\\/]{2}(?:[?.][\\\\/]UNC[\\\\/])?[^\\\\/]+[\\\\/][^\\\\/]+)~i',
                $directory,
                $root
            )) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            return $root[0] . $path;
        }

        if (str_starts_with($path, '/') || str_contains($path, '://')
            || ('\\' === \DIRECTORY_SEPARATOR && (str_starts_with($path, '\\') || 1 === preg_match(
                '~^[a-zA-Z]:[\\\\/]~',
                $path
            )))) {
            return $path;
        }

        if ('\\' === \DIRECTORY_SEPARATOR && 1 === preg_match('~^[a-zA-Z]:~', $path)) {
            $resolved = realpath($path);
            if (false === $resolved) {
                throw InvalidArgumentException::forUnreadableFile($this->file);
            }

            return $resolved;
        }

        $directory ??= getcwd();
        if (false === $directory) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        return $directory . \DIRECTORY_SEPARATOR . $path;
    }
}
