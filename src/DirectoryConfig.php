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

use FastForward\Config\Exception\InvalidArgumentException;
use Laminas\ConfigAggregator\PhpFileProvider;

/**
 * Class DirectoryConfig.
 *
 * Loads and aggregates configuration from a specified directory containing PHP files.
 * This class MUST validate the target directory and use the Laminas PhpFileProvider to collect configurations.
 * It MAY cache the aggregated result if a cached config file path is provided.
 *
 * @extends LamiasConfigAggregatorConfig
 */
class DirectoryConfig extends LamiasConfigAggregatorConfig implements ConfigInterface
{
    use LazyLoadConfigTrait;

    /**
     * @const string File matching pattern for PHP configuration files.
     */
    protected const PATTERN = '{,*}.php';

    /**
     * Constructs a DirectoryConfig instance.
     *
     * This constructor SHALL validate the specified directory, and initialize
     * the Laminas config aggregator with a PHP file provider using the defined pattern.
     * If a cache file is provided, it SHALL be used to store the aggregated configuration.
     *
     * @param string      $directory        the directory path from which to load configuration files
     * @param null|string $cachedConfigFile optional path to a cache file for the aggregated configuration
     *
     * @throws InvalidArgumentException if the directory is not valid or not readable
     */
    public function __construct(
        private readonly string $directory,
        private readonly ?string $cachedConfigFile = null,
    ) {
        if (!is_dir($this->directory) || !is_readable($this->directory)) {
            throw InvalidArgumentException::forUnreadableDirectory($this->directory);
        }

        $pattern = mb_rtrim($this->directory, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . static::PATTERN;

        parent::__construct(
            providers: [new PhpFileProvider($pattern)],
            cachedConfigFile: $this->cachedConfigFile,
        );
    }
}
