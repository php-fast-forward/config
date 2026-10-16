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

/**
 * Class RecursiveDirectoryConfig.
 *
 * Extends DirectoryConfig to support recursive loading of configuration files from a root directory.
 * This class SHALL match PHP files at every depth using the parent directory traversal.
 */
final class RecursiveDirectoryConfig extends DirectoryConfig
{
    use LazyLoadConfigTrait;

    /**
     * @var string filename pattern applied independently in every directory
     */
    protected const PATTERN = '*.php';

    /**
     * @var bool whether child directories should be discovered
     */
    protected const RECURSIVE = true;
}
