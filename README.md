# FastForward Config

**FastForward Config** is a flexible and modern PHP configuration library built for performance, extendability, and lazy-loading behavior. It supports dot-notation keys, recursive directory loading, Laminas-compliant configuration providers, and optional PSR-16 caching.

---

## ✨ Features

- 🔑 Dot notation access: `config->get('app.env')`
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
use FastForward\Config\{config, configDir, configCache};
use Symfony\Component\Cache\Simple\FilesystemCache;

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
$cache = new FilesystemCache();

$config = configCache(
    cache: $cache,
    ['foo' => 'bar']
);

echo $config->get('foo'); // "bar"
```

---

### Load from a recursive directory:

```php
$config = configDir(__DIR__ . '/config', recursive: true);
```

---

### Use Laminas-style providers:

```php
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
- `configDir(string $dir, bool $recursive = false, ?string $cache = null): ConfigInterface`
- `configProvider(iterable $providers, ?string $cache = null): ConfigInterface`

---

## 🛡 License

MIT © 2025 [Felipe Sayão Lobato Abreu](https://github.com/mentordosnerds)
