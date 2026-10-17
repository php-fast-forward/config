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

namespace FastForward\Config\SkillExamples;

use RuntimeException;

if (($argv[1] ?? null) === '--help') {
    echo 'Usage: php ', basename($argv[0]), ' [consumer/vendor/autoload.php]', \PHP_EOL;
    exit(0);
}

$autoload = $argv[1] ?? getcwd() . '/vendor/autoload.php';
if (! is_file($autoload) || ! is_readable($autoload)) {
    throw new RuntimeException('Pass the readable Composer autoloader of the consumer as the first argument.');
}

require $autoload;

function same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}
