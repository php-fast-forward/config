# FastForward Config

[![PHP Version](https://img.shields.io/badge/php-^8.3-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/)
[![Tests](https://img.shields.io/github/actions/workflow/status/php-fast-forward/config/tests.yml?logo=githubactions&logoColor=white&label=tests&color=22C55E)](https://github.com/php-fast-forward/config/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/badge/coverage-phpunit-4ADE80?logo=php&logoColor=white)](https://php-fast-forward.github.io/config/coverage/index.html)
[![Docs](https://img.shields.io/github/deployments/php-fast-forward/config/github-pages?logo=readthedocs&logoColor=white&label=docs&labelColor=1E293B&color=38BDF8&style=flat)](https://php-fast-forward.github.io/config/index.html)
[![License](https://img.shields.io/github/license/php-fast-forward/config?color=64748B)](LICENSE)
[![GitHub Sponsors](https://img.shields.io/github/sponsors/php-fast-forward?logo=githubsponsors&logoColor=white&color=EC4899)](https://github.com/sponsors/php-fast-forward)

**FastForward Config** is a flexible and modern PHP configuration library built for performance, extendability, and lazy-loading behavior. It supports dot-notation keys, recursive directory loading, Laminas-compliant configuration providers, and optional PSR-16 caching.

---

## ✨ Features

- 🔑 Dot notation access: `$config->get('app.env')`
- 📁 Load from arrays, directories, or providers
- ♻️ Lazy-loading with `__invoke()`
- 🧩 Aggregation of multiple sources
- 🗂 Recursive directory support
- 💾 Optional PSR-16 compatible caching
- 🔌 Compatible with Laminas ConfigProviders

---

## 📦 Installation

```bash
composer require fast-forward/config
```

---

## 🚀 Quick Start

### Load configuration from multiple sources:

```php
use function FastForward\Config\config;

$config = config(
    ['app' => ['env' => 'production']],
    __DIR__ . '/config',
    \Vendor\Package\ConfigProvider::class
);

echo $config->get('app.env'); // "production"
```

---

### Cache configuration using PSR-16:

```php
use function FastForward\Config\configCache;

/** @var \Psr\SimpleCache\CacheInterface $cache */
$config = configCache($cache, ['foo' => 'bar']);

echo $config->get('foo'); // "bar"
```

---

### Load from a recursive directory:

```php
use function FastForward\Config\configDir;

$config = configDir(__DIR__ . '/config', recursive: true);
```

---

### Use Laminas-style providers:

```php
use function FastForward\Config\configProvider;

$config = configProvider([
    new Vendor\Package\Provider1(),
    new Vendor\Package\Provider2(),
]);
```

---

## 🧪 Access & Mutation

```php
$config->set('db.host', 'localhost');
echo $config->get('db.host'); // "localhost"

$config->has('app.debug'); // true/false

print_r($config->toArray());
```

---

## 📁 Directory Structure Example

```
config/
├── app.php
├── db.php
└── services/
    └── mail.php
```

---

## 🧰 API Summary

- `config(...$configs): ConfigInterface`
- `configCache(CacheInterface $cache, ...$configs): ConfigInterface`
- `configDir(string $rootDirectory, bool $recursive = false, ?string $cachedConfigFile = null): ConfigInterface`
- `configProvider(iterable $providers, ?string $cachedConfigFile = null): ConfigInterface`

---

## 🛡 License

MIT © 2025 [Felipe Sayão Lobato Abreu](https://github.com/mentordosnerds)
