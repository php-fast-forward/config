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

use FastForward\Config\ArrayConfig;
use FastForward\Config\CachedConfig;
use FastForward\Config\SkillExamples\Fixtures\MemoryCache;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/Fixtures/InvalidCacheKeyException.php';
require __DIR__ . '/Fixtures/MemoryCache.php';

$cache = new MemoryCache();
$source = new ArrayConfig([
    'app' => [
        'env' => 'production',
        'remove_me' => true,
    ],
]);
$memory = new CachedConfig($cache, $source, cacheKey: 'storefront.config.v1');
same('production', $memory->get('app.env'), 'A miss must populate from the source.');
$memory->set('app.env', 'memory-only');
same('production', $cache->get('storefront.config.v1')['app']['env'], 'Non-persistent mutation must remain local.');

$persistent = new CachedConfig($cache, $source, persistent: true, cacheKey: 'storefront.config.v1');
$persistent->set('app.env', 'staging');
$persistent->remove('app.remove_me');
$reloaded = new CachedConfig($cache, $source, cacheKey: 'storefront.config.v1');
same('staging', $reloaded->get('app.env'), 'Persistent cache mutation must survive a fresh wrapper.');
same(false, $reloaded->has('app.remove_me'), 'Persistent cache removal must survive a fresh wrapper.');
same('production', $source->get('app.env'), 'Cache persistence must not mutate the source object.');

$other = new CachedConfig($cache, new ArrayConfig([
    'app' => [
        'env' => 'other',
    ],
]), cacheKey: 'other.config.v1');
same('other', $other->get('app.env'), 'Separate source identities must remain isolated.');
$cache->delete('storefront.config.v1');
$rebuilt = new CachedConfig($cache, $source, cacheKey: 'storefront.config.v1');
same('production', $rebuilt->get('app.env'), 'Explicit invalidation must allow reconstruction.');

same(null, $cache->ttls['storefront.config.v1'], 'An explicit key must preserve the backend default TTL.');
$isolatedCache = new MemoryCache();
$first = new CachedConfig($isolatedCache, new ArrayConfig(['value' => 'first']), persistent: true);
$second = new CachedConfig($isolatedCache, new ArrayConfig(['value' => 'second']));
same('first', $first->get('value'), 'An isolated source must resolve.');
same('second', $second->get('value'), 'Default keys must not collide across wrappers.');
$first->set('value', 'changed');
$first->remove('value');
same([300, 300], array_values($isolatedCache->ttls), 'Isolated writes must use a bounded TTL.');

$declining = new MemoryCache();
$declining->acceptWrites = false;
$failed = new CachedConfig($declining, new ArrayConfig(['value' => 'available']), persistent: true);
same('available', $failed->get('value'), 'A declined initial write must not discard source data.');
$caught = false;
try {
    $failed->set('value', 'local-recovery');
} catch (\RuntimeException) {
    $caught = true;
}
same(true, $caught, 'A declined persistent write must be reported.');
same('local-recovery', $failed->get('value'), 'The local mutation must remain available for recovery.');

echo 'caching: ok', \PHP_EOL;
