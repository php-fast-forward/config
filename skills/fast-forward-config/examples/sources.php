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

use Laminas\ConfigAggregator\ConfigAggregator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function FastForward\Config\config;
use function FastForward\Config\configDir;
use function FastForward\Config\configFile;
use function FastForward\Config\configProvider;

require __DIR__ . '/bootstrap.php';

$directory = sys_get_temp_dir() . '/ff-config-skill-' . bin2hex(random_bytes(8));
if (! mkdir($directory, 0o700)) {
    throw new RuntimeException('Cannot create the private example directory.');
}

try {
    mkdir($directory . '/config', 0o700);
    mkdir($directory . '/config/module', 0o700);
    file_put_contents(
        $directory . '/config/10-default.php',
        '<?php return ["app" => ["env" => "production"], "jobs" => ["base"]];'
    );
    file_put_contents(
        $directory . '/config/20-local.php',
        '<?php return ["app" => ["env" => "testing"], "jobs" => ["local"]];'
    );
    file_put_contents($directory . '/config/module/30-module.php', '<?php return ["module" => true];');

    $root = configDir($directory . '/config');
    same('testing', $root->get('app.env'), 'Root PHP files must apply in ordinary sorted filename order.');
    same(['base', 'local'], $root->get('jobs'), 'File aggregation must retain sequential-list order.');
    same(false, $root->has('module'), 'Non-recursive loading must ignore nested files.');
    same(
        true,
        configDir($directory . '/config', recursive: true)->get('module'),
        'The baseline supports one nested level.'
    );
    same(true, config($directory . '/config')->get('module'), 'A directory string must select recursive mode.');

    $file = $directory . '/runtime.php';
    $persistent = configFile($file, persistent: true, defaultConfig: [
        'app' => [
            'env' => 'default',
        ],
    ]);
    same('default', $persistent->get('app.env'), 'Missing-file defaults must resolve.');
    same(false, file_exists($file), 'Reading defaults must not create the file.');
    $persistent->set('app.env', 'persisted');
    same('persisted', configFile($file)->get('app.env'), 'Persistent mutation must survive a new wrapper.');
    $memory = configFile($file);
    $memory->set('app.env', 'memory-only');
    same('persisted', configFile($file)->get('app.env'), 'Non-persistent mutation must not edit the file.');

    $calls = 0;
    $providers = configProvider([
        static function () use (&$calls): array {
            ++$calls;

            return [
                'app' => [
                    'env' => 'early',
                ],
            ];
        },
        static fn(): array => [
            'app' => [
                'env' => 'late',
            ],
        ],
    ]);
    same(0, $calls, 'Provider construction must be lazy.');
    same('late', $providers->get('app.env'), 'Consistent nested providers must use later scalar precedence.');
    same(1, $calls, 'The provider must be evaluated once by the resolved wrapper.');
    $providers->toArray();
    same(1, $calls, 'Repeated wrapper access must reuse its snapshot.');

    $disabledCache = $directory . '/disabled-cache.php';
    configProvider([
        static fn(): array => [
            'value' => 'uncached',
        ],
    ], $disabledCache)->toArray();
    same(false, file_exists($disabledCache), 'A file-cache path alone must not enable generation.');

    $cacheFile = $directory . '/providers-cache.php';
    configProvider([
        static fn(): array => [
            ConfigAggregator::ENABLE_CACHE => true,
            'value' => 'cached',
        ],
    ], $cacheFile)->toArray();
    same(true, file_exists($cacheFile), 'The enabled file cache must be generated.');
    $cached = configProvider([
        static function (): array {
            throw new RuntimeException('A cache hit must not evaluate providers.');
        },
    ], $cacheFile);
    same('cached', $cached->get('value'), 'A new provider wrapper must reuse the generated file.');
} finally {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($entries as $entry) {
        if ($entry->isDir() && ! $entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($directory);
}

echo 'sources: ok', \PHP_EOL;
