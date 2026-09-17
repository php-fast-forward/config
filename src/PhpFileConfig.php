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
    ) {
    }

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
            $this->getConfig()->set($key, $value);

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
            $this->getConfig()->remove($key);

            return;
        }

        $this->mutate(static function (ConfigInterface $config) use ($key): void {
            $config->remove($key);
        });
    }

    /**
     * Loads the configuration from the file or default configuration.
     *
     * @return ConfigInterface
     *
     * @throws InvalidArgumentException if the file is unreadable or does not return an array
     */
    private function loadConfig(): ConfigInterface
    {
        if (! file_exists($this->file)) {
            if ($this->defaultConfig !== null) {
                $data = $this->defaultConfig instanceof ConfigInterface
                    ? $this->defaultConfig->toArray()
                    : $this->defaultConfig;

                return new ArrayConfig($data);
            }

            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        if (! is_file($this->file) || ! is_readable($this->file)) {
            throw InvalidArgumentException::forUnreadableFile($this->file);
        }

        $data = (static function (string $file): mixed {
            return require $file;
        })($this->file);

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
        $dir = \dirname($this->file);

        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }
        } elseif (! is_writable($dir)) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        if (file_exists($this->file) && (! is_file($this->file) || ! is_writable($this->file))) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        $lockPath = $dir . '/.' . \basename($this->file) . '.lock';
        $lockFp   = @fopen($lockPath, 'c+');

        if (false === $lockFp) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        try {
            if (! @flock($lockFp, \LOCK_EX)) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }

            try {
                $config = $this->loadConfig();
                $operation($config);
                $this->dump($config->toArray());
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
     *
     * @return void
     *
     * @throws InvalidArgumentException if the file or directory is not writable, or cannot be exported
     */
    private function dump(array $data): void
    {
        $dir = \dirname($this->file);

        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
                throw InvalidArgumentException::forUnwritableFile($this->file);
            }
        } elseif (! is_writable($dir)) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        if (file_exists($this->file) && (! is_file($this->file) || ! is_writable($this->file))) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        try {
            $exported = VarExporter::export($data);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException($e->getMessage(), (int) $e->getCode(), $e);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$exported};\n";

        $tmpFile = tempnam($dir, 'cfg_');
        if (false === $tmpFile) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        if (false === @file_put_contents($tmpFile, $content, \LOCK_EX)) {
            @unlink($tmpFile);
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        @chmod($tmpFile, 0666 & ~umask());

        if (! @rename($tmpFile, $this->file)) {
            @unlink($tmpFile);
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        if (\function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->file, true);
        }
    }
}
