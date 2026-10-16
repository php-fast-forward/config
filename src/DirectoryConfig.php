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

use DirectoryIterator;
use FastForward\Config\Exception\InvalidArgumentException;
use Generator;
use Laminas\ConfigAggregator\GlobTrait;
use UnexpectedValueException;

/**
 * Class DirectoryConfig.
 *
 * Loads and aggregates configuration from a specified directory containing PHP files.
 * This class MUST validate the target directory and match file names using Laminas glob semantics.
 * It MAY cache the aggregated result if a cached config file path is provided.
 *
 * @extends LaminasConfigAggregatorConfig
 */
class DirectoryConfig extends LaminasConfigAggregatorConfig implements ConfigInterface
{
    use GlobTrait;
    use LazyLoadConfigTrait;

    /**
     * @var string file matching pattern for PHP configuration files
     */
    protected const PATTERN = '{,*}.php';

    /**
     * @var bool whether child directories should be discovered
     */
    protected const RECURSIVE = false;

    /**
     * Constructs a DirectoryConfig instance.
     *
     * This constructor SHALL validate the specified directory, and initialize
     * the Laminas config aggregator with a lazy file provider using the defined pattern.
     * If a cache file is provided, it SHALL be used to store the aggregated configuration.
     *
     * @param string $directory the directory path from which to load configuration files
     * @param string|null $cachedConfigFile optional path to a cache file for the aggregated configuration
     *
     * @throws InvalidArgumentException if the directory is not valid or not readable
     */
    public function __construct(
        private readonly string $directory,
        private readonly ?string $cachedConfigFile = null,
    ) {
        if (! is_dir($this->directory) || ! is_readable($this->directory)) {
            throw InvalidArgumentException::forUnreadableDirectory($this->directory);
        }

        parent::__construct(
            providers: [$this->provideConfigs(...)],
            cachedConfigFile: $this->cachedConfigFile,
        );
    }

    /**
     * Includes discovered PHP files only when the aggregator resolves this provider.
     *
     * @return Generator
     */
    private function provideConfigs(): Generator
    {
        foreach ($this->discoverFiles() as $file) {
            yield include $file;
        }
    }

    /**
     * Discovers files in depth-then-path order without following directory symlinks.
     *
     * @return list<string>
     */
    private function discoverFiles(): array
    {
        $directories = [$this->directory];
        $seenDirectories = [];
        $seenFiles = [];
        $files = [];

        while ([] !== $directories) {
            usort($directories, static fn(string $first, string $second): int => strcmp($first . '/', $second . '/'));
            $nextDirectories = [];
            $levelFiles = [];
            foreach ($directories as $directory) {
                $realDirectory = realpath($directory);
                if (false === $realDirectory) {
                    continue;
                }

                if (isset($seenDirectories[$realDirectory])) {
                    continue;
                }

                $seenDirectories[$realDirectory] = true;
                $pattern = $this->escapeDirectory($directory) . '/' . static::PATTERN;
                foreach ($this->glob($pattern) as $file) {
                    $file = (string) $file;
                    if (is_file($file) && $this->isWithinDirectory($file, $realDirectory)) {
                        $levelFiles[] = $file;
                    }
                }

                if (! static::RECURSIVE) {
                    continue;
                }

                $iterator = $this->openDirectory($directory);
                if (! $iterator instanceof DirectoryIterator) {
                    continue;
                }

                foreach ($iterator as $entry) {
                    if ($entry->isDir() && ! $entry->isLink() && ! str_starts_with($entry->getFilename(), '.')) {
                        $nextDirectories[] = $entry->getPathname();
                    }
                }
            }

            foreach ($levelFiles as $file) {
                $realFile = realpath($file);
                if (false === $realFile) {
                    continue;
                }

                if (isset($seenFiles[$realFile])) {
                    continue;
                }

                $seenFiles[$realFile] = true;
                $files[] = $realFile;
            }

            $directories = $nextDirectories;
        }

        return $files;
    }

    /**
     * Skips children that become inaccessible between discovery and opening.
     *
     * @param string $directory the discovered directory path
     *
     * @return DirectoryIterator|null
     *
     * @throws UnexpectedValueException for root failures or unrelated directory-opening errors
     */
    private function openDirectory(string $directory): ?DirectoryIterator
    {
        try {
            return new DirectoryIterator($directory);
        } catch (UnexpectedValueException $unexpectedValueException) {
            clearstatcache(true, $directory);
            if ($directory !== $this->directory && (! is_dir($directory) || ! is_readable($directory))) {
                return null;
            }

            throw $unexpectedValueException;
        }
    }

    /**
     * Checks a candidate's canonical parent against the selected literal directory.
     *
     * @param string $file the candidate path
     * @param string $directory the canonical selected directory
     *
     * @return bool
     */
    private function isWithinDirectory(string $file, string $directory): bool
    {
        $parent = realpath(\dirname($file));
        if (false === $parent) {
            return false;
        }

        $prefix = rtrim($directory, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;

        if ('\\' === \DIRECTORY_SEPARATOR) {
            return 0 === strcasecmp($directory, $parent) || 0 === strncasecmp($parent, $prefix, \strlen($prefix));
        }

        return $directory === $parent || str_starts_with($parent, $prefix);
    }

    /**
     * Escapes only the directory, leaving the library's filename pattern intact.
     *
     * @param string $directory the literal directory path
     *
     * @return string
     */
    private function escapeDirectory(string $directory): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $directory = str_replace('\\', '/', $directory);

            // Windows glob forces GLOB_NOESCAPE. Verify candidate parent identities before including files.
            return strtr(rtrim($directory, '/'), [
                '[' => '[[]',
                ']' => '[]]',
                '{' => '?',
                '}' => '?',
            ]);
        }

        return strtr(rtrim($directory, '/'), [
            '\\' => '\\\\',
            '[' => '\\[',
            ']' => '\\]',
            '*' => '\\*',
            '?' => '\\?',
            '{' => '\\{',
            '}' => '\\}',
        ]);
    }
}
