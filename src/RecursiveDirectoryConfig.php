<?php

declare(strict_types=1);

/**
 * This file is part of php-fast-forward/config.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/php-fast-forward/config
 * @copyright Copyright (c) 2025 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace FastForward\Config;

/**
 * Class RecursiveDirectoryConfig.
 *
 * Extends DirectoryConfig to support recursive loading of configuration files from a root directory.
 * This class SHALL match both top-level and nested PHP files using a recursive glob pattern.
 */
final class RecursiveDirectoryConfig extends DirectoryConfig
{
    use LazyLoadConfigTrait;

    /**
     * @const string Recursive pattern to match all *.php files in all nested directories.
     */
    protected const PATTERN = '{*,**/*}.php';

    /**
     * Constructs a RecursiveDirectoryConfig instance.
     *
     * This constructor SHALL initialize the parent DirectoryConfig with a modified pattern
     * that supports recursive file discovery. It MAY optionally use a cached configuration file.
     *
     * @param string      $rootDirectory    the root directory to search recursively for config files
     * @param null|string $cachedConfigFile optional path to a cache file for aggregated configuration
     */
    public function __construct(string $rootDirectory, ?string $cachedConfigFile = null)
    {
        parent::__construct(
            directory: $rootDirectory,
            cachedConfigFile: $cachedConfigFile,
        );
    }
}
