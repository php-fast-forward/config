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

use function FastForward\Config\configDir;

require __DIR__ . '/bootstrap.php';

$config = configDir(__DIR__ . '/config/autoload');
same(true, $config->get('app.debug'), 'The local dotted key must override the nested default.');
same('production', $config->get('app.env'), 'Unchanged defaults must remain available.');
same(['email', 'metrics', 'email'], $config->get('jobs'), 'Lists must retain ordered duplicates.');
same('fallback', $config->get('app.absent', 'fallback'), 'An absent optional value must use its default.');

echo 'application: ok', \PHP_EOL;
