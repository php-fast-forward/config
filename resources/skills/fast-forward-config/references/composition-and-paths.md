# Composition and paths

Load for source conflicts, path handling, map/list shape or mutation. Run `examples/composition.php` with a consumer autoloader.

## Aggregation and snapshots

`config($defaults, $overrides)` and `new AggregateConfig($defaultsConfig, $overridesConfig)` resolve sources in order; later scalar values win. Sequential lists concatenate with duplicates retained. Maps merge recursively. A sparse numeric or mixed-key array is not a sequential list; characterize conflicting numeric keys instead of assuming append semantics.

Input arrays normalize only when all their keys are strings. Nested all-string-key maps normalize recursively, while lists and mixed-key arrays preserve their contents, including separator-bearing keys inside them. Keep config maps and collections distinct and test exact exported arrays.

Lazy wrappers memoize their resolved config on the first `get()`, `has()`, iteration, export or mutation. Changing an input after resolving an aggregate does not refresh its snapshot. Build a new aggregate when you need updated sources. Calling `__invoke()` resolves a separate result; use normal wrapper methods for a consistent mutable snapshot.

## Mutation is recursive replacement

```php
$config = new FastForward\Config\ArrayConfig(['jobs' => ['email', 'metrics']]);
$config->set('jobs', ['sms']);
// ['sms', 'metrics']

$config->remove('jobs');
$config->set('jobs', ['sms']);
// ['sms']
```

`set(['app.debug' => true])` accepts an array; `set($otherConfig)` accepts another config. A separate non-null value requires a string key. Persistent wrappers apply the same mutation semantics.

## Null, maps and paths

```php
$config = new FastForward\Config\ArrayConfig([
    'app' => ['label' => null, 'options' => []],
]);
$config->has('app.label');              // true
$config->get('app.label', 'fallback');   // 'fallback'
$config->has('app.missing');            // false
$config->get('app.options');            // ArrayConfig, not []
$config->get('app')->toArray();         // ['label' => null, 'options' => []]
```

The returned map is an independent wrapper; call `$config->set('app.label', 'Store')` to update the owner. There is no key escaping: `db/host` and `db.host` select the same path. `a..b` selects an empty middle segment. An empty string passed to `has/get/remove` throws `FastForward\Config\Exception\InvalidArgumentException`. `set('')` can store an empty root key, visible through export but inaccessible through that empty lookup path; avoid such keys in application configuration.

ArrayAccess delegates to those operations. `isset` returns true for present nulls. `toArray()` preserves nested shape; flattened iteration omits empty arrays and does not round-trip collection boundaries.
