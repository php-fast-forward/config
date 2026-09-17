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
     * the default configuration SHALL be used and optionally written to disk if persistent.
     *
     * @return ConfigInterface a ConfigInterface implementation containing the configuration data
     *
     * @throws InvalidArgumentException if the file is unreadable, unwritable, or does not return an array
     */
    public function __invoke(): ConfigInterface
    {
        if (! file_exists($this->file)) {
            if ($this->defaultConfig !== null) {
                $data = $this->defaultConfig instanceof ConfigInterface
                    ? $this->defaultConfig->toArray()
                    : $this->defaultConfig;

                if ($this->persistent) {
                    $this->dump($data);
                }

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
        $config = $this->getConfig();
        $config->set($key, $value);

        if ($this->persistent) {
            $this->dump($config->toArray());
        }
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
        $config = $this->getConfig();
        $config->remove($key);

        if ($this->persistent) {
            $this->dump($config->toArray());
        }
    }

    /**
     * Dumps the configuration array to the PHP file.
     *
     * @param array<array-key, mixed> $data the configuration data to write
     *
     * @return void
     *
     * @throws InvalidArgumentException if the file or directory is not writable
     */
    private function dump(array $data): void

    {
        $dir = \dirname($this->file);

        if (! is_dir($dir) || ! is_writable($dir)) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        if (file_exists($this->file) && ! is_writable($this->file)) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }

        $exported = class_exists(VarExporter::class)
            ? VarExporter::export($data)
            : var_export($data, true);

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$exported};\n";

        if (@file_put_contents($this->file, $content, \LOCK_EX) === false) {
            throw InvalidArgumentException::forUnwritableFile($this->file);
        }
    }
}
