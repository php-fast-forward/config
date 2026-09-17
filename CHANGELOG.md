# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2026-09-17

### Added

- Add PhpFileConfig with optional persistence and configFile() helper
- Add comprehensive Sphinx documentation under docs/

### Changed

- Raise minimum PHP version requirement to ^8.3
- Implement atomic file persistence using brick/varexporter and temporary files in PhpFileConfig
- Restrict automatic PHP file detection in config() helper to files ending with .php

### Deprecated

- Deprecate LamiasConfigAggregatorConfig in favor of LaminasConfigAggregatorConfig

### Fixed

- Allow setting falsy values (false, 0, '0', '', null) with string keys in ArrayConfig::set()
- Synchronize multi-instance persistent file mutations via sidecar process locking and fresh file reloads in PhpFileConfig
- Correct Sphinx section underline markup for LamiasConfigAggregatorConfig in docs/api-interfaces.rst
- Preserve restrictive file permissions when atomically persisting configuration
- Preserve symlink targets and update real file target during persistent writes
- Verify complete byte count on temporary file write to prevent partial file corruption
- Rename typo LamiasConfigAggregatorConfig to LaminasConfigAggregatorConfig while retaining LamiasConfigAggregatorConfig as a deprecated compatibility alias

## [1.4.0] - 2025-06-11

### Added

- Implement ArrayAccess in ConfigInterface via ArrayAccessConfigTrait
- Add remove() method to ConfigInterface for removing configuration keys

## [1.3.0] - 2025-05-24

### Added

- Introduce ConfigHelper with isAssoc(), normalize(), and flatten() utilities
- Support directory path strings directly in config() helper

### Changed

- Update getIterator() across config classes to yield flattened dot-notated key-value pairs

## [1.2.0] - 2025-05-23

### Added

- Support custom cache keys and persistent cache updates on set() in CachedConfig

## [1.1.4] - 2025-04-20

### Fixed

- Require 'config.' prefix for configuration key lookup in ConfigContainer

## [1.1.3] - 2025-04-20

### Fixed

- Return wrapped configuration instance when resolved by container alias in ConfigContainer
- Correct package name in installation instructions in README.md

## [1.1.2] - 2025-04-19

### Fixed

- Rename ContainerNotFoundExceptionInterface to ContainerNotFoundException

## [1.1.1] - 2025-04-18

### Fixed

- Preserve sequential arrays and non-string keys during array normalization in ArrayConfig

## [1.1.0] - 2025-04-18

### Added

- Add PSR-11 container implementation via ConfigContainer

## [1.0.0] - 2025-04-18

### Added

- Initial release of FastForward Config library
- In-memory configuration storage with dot-notation support via ArrayConfig
- Directory and recursive directory configuration loaders via DirectoryConfig and RecursiveDirectoryConfig
- PSR-16 cache wrapper via CachedConfig
- Laminas ConfigAggregator bridge via LamiasConfigAggregatorConfig
- Helper functions config(), configCache(), configDir(), and configProvider()


[unreleased]: https://github.com/php-fast-forward/config/compare/v1.5.0...HEAD
[1.5.0]: https://github.com/php-fast-forward/config/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/php-fast-forward/config/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/php-fast-forward/config/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/php-fast-forward/config/compare/v1.1.4...v1.2.0
[1.1.4]: https://github.com/php-fast-forward/config/compare/v1.1.3...v1.1.4
[1.1.3]: https://github.com/php-fast-forward/config/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/php-fast-forward/config/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/php-fast-forward/config/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/php-fast-forward/config/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/php-fast-forward/config/releases/tag/v1.0.0
