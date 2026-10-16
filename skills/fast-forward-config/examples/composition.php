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

use FastForward\Config\AggregateConfig;
use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use FastForward\Config\Exception\InvalidArgumentException;

use function FastForward\Config\config;

require __DIR__ . '/bootstrap.php';

$base = new ArrayConfig([
    'app' => [
        'env' => 'production',
        'debug' => false,
        'nullable' => null,
        'empty' => [],
    ],
    'jobs' => ['email', 'metrics'],
]);
$config = config($base, [
    'app.debug' => true,
    'jobs' => ['email'],
]);
same(true, $config->get('app/debug'), 'Later scalar/path override failed.');
same(['email', 'metrics', 'email'], $config->get('jobs'), 'Sequential lists must retain ordered duplicates.');
same(true, $config->has('app.nullable'), 'A present null must exist.');
same('default', $config->get('app.nullable', 'default'), 'Null must use the requested default.');
same(false, $config->has('app.absent'), 'An absent key must not exist.');
same(true, $config->get('app.empty') instanceof ConfigInterface, 'Empty maps must be wrapped.');
same([], $config->get('app.empty')->toArray(), 'Empty-map export failed.');

$map = $config->get('app');
$map->set('env', 'detached');
same('production', $config->get('app.env'), 'A returned map must be an independent value.');

$config->set('jobs', ['sms']);
same(['sms', 'metrics', 'email'], $config->get('jobs'), 'set() must replace matching numeric indices.');
$config->remove('jobs');
$config['jobs'] = ['sms'];
same(['sms'], $config['jobs'], 'Remove then set must replace the complete list.');
same(true, isset($config['app.nullable']), 'ArrayAccess existence must include present nulls.');
unset($config['app.nullable']);
same(false, $config->has('app.nullable'), 'ArrayAccess removal failed.');
same(['sms'], $config->toArray()['jobs'], 'Export must keep nested list shape.');
same('sms', iterator_to_array($config)['jobs.0'], 'Iteration must flatten list indices.');
same(false, \array_key_exists('app.empty', iterator_to_array($config)), 'Iteration must omit empty arrays.');

$config->set('segments..leaf', 'empty-segment');
same('empty-segment', $config->get('segments//leaf'), 'Repeated delimiters must keep empty segments.');
foreach (['get', 'has', 'remove'] as $operation) {
    $caught = false;
    try {
        $config->{$operation}('');
    } catch (InvalidArgumentException) {
        $caught = true;
    }

    same(true, $caught, 'Empty paths must raise the library exception.');
}

$base->set('app.env', 'changed-source');
same('production', $config->get('app.env'), 'A resolved aggregate must keep its snapshot.');
$fresh = new AggregateConfig($base);
same('changed-source', $fresh->get('app.env'), 'A fresh aggregate must observe source changes.');

$mixed = new ArrayConfig([
    'collection' => [
        2 => 'sparse',
        'raw.path' => 'literal',
    ],
]);
same([
    2 => 'sparse',
    'raw.path' => 'literal',
], $mixed->get('collection'), 'Mixed collections must remain raw arrays.');

echo 'composition: ok', \PHP_EOL;
