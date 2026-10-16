# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Removed

- Remove the deprecated misspelled LamiasConfigAggregatorConfig class without an autoload alias fallback. This is an intentional compatibility break: replace imports, type declarations, and instantiations with LaminasConfigAggregatorConfig.

### Fixed

- Normalize each provider or PHP file's dot and slash paths before Laminas merges sources, preserving later-source precedence, list order, merge instructions, lazy loading, and file-cache behavior.
- Load, lock, and atomically replace the same resolved PhpFileConfig target, avoiding competing include_path files and anchoring relative inputs on first use across working-directory changes while following current symlink targets.
- Follow dangling symlink chains to their final target during persistent mutations, preserving intermediate links and rejecting cyclic or unresolvable chains before replacement.
- Preserve existing POSIX file groups and permission bits during atomic persistence, rejecting replacements with InvalidArgumentException when required metadata cannot be retained or its functions are disabled.
- Document and test the explicit Laminas config_cache_enabled flag, writable parent requirements, and existing-file reuse for provider and directory file-cache examples.

## [1.6.0] - 2026-10-16

### Changed

- Replace dflydev/dot-access-data with native array storage, path traversal, normalization, and merging while preserving configuration lookup, assignment, and aggregation behavior. Empty lookup paths now raise FastForward\Config\Exception\InvalidArgumentException instead of the removed dependency's exception.

### Fixed

- Restore Wiki API documentation generation and main-branch publication, preserve Wiki history and unrelated pages, normalize internal page links, and verify published content.

## [1.5.1] - 2026-10-09

### Added

- Add a contextual repository mascot banner to the README.

### Fixed

- Restore Reports generation and publish documentation, coverage, and metrics through native GitHub Pages artifact deployment, with site verification after deployment and job-scoped permissions.
- Concatenate sequential lists when aggregating configuration sources, preserving order and duplicates, recursive map merging, later scalar precedence, dot notation, and lazy loading without changing explicit ArrayConfig::set() index-replacement semantics.
- Mirror verified Dependabot push test outcomes through a separate completion workflow so required statuses are available with the bot's restricted token.
- Allow the isolated CI status publisher to read workflow job outcomes without granting write permissions to test jobs.

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


[unreleased]: https://github.com/php-fast-forward/config/compare/v1.6.0...HEAD
[1.6.0]: https://github.com/php-fast-forward/config/compare/v1.5.1...v1.6.0
[1.5.1]: https://github.com/php-fast-forward/config/compare/v1.5.0...v1.5.1
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
