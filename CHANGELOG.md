# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.3] - 2026-10-20

### Changed

- Update repository Changelog guidance for the current lifecycle and show both current and next version tags in generated release pull request titles. ([#44](https://github.com/php-fast-forward/config/pull/44))

## [1.7.2] - 2026-10-18

### Changed

- Publish the release tag and GitHub Release after an approved managed version pull request is merged. ([#42](https://github.com/php-fast-forward/config/pull/42))
- Limit automatic PHP tests to code and test inputs, and regenerate Reports and Wiki only when their source or documentation inputs change, while preserving manual workflow execution. ([#41](https://github.com/php-fast-forward/config/pull/41))

## [1.7.1] - 2026-10-18

### Changed

- Adopt independent Fast Forward Changelog fragments and reviewed version pull requests with repository-local contributor instructions, and reconcile historical publication dates and delivery pull-request references. ([#39](https://github.com/php-fast-forward/config/pull/39))

## [1.7.0] - 2026-10-17

### Added

- Add a portable `fast-forward-config` consumer skill in resources/skills with version-aware guidance, checked application examples, manual project/global installation and optional llm/skills Composer discovery. ([#34](https://github.com/php-fast-forward/config/pull/34))

### Changed

- Give each CachedConfig wrapper an isolated, portable PSR-16 default key whose entries expire after 300 seconds. Cross-instance or cross-request reuse now requires an explicit cacheKey; old class-name entries are not reused. ([#31](https://github.com/php-fast-forward/config/pull/31))

### Removed

- Remove the deprecated misspelled LamiasConfigAggregatorConfig class without an autoload alias fallback. This is an intentional compatibility break: replace imports, type declarations, and instantiations with LaminasConfigAggregatorConfig. ([#32](https://github.com/php-fast-forward/config/pull/32))

### Fixed

- Normalize each provider or PHP file's dot and slash paths before Laminas merges sources, preserving later-source precedence, list order, merge instructions, lazy loading, and file-cache behavior. ([#35](https://github.com/php-fast-forward/config/pull/35))
- Load, lock, and atomically replace the same resolved PhpFileConfig target, avoiding competing include_path files and anchoring relative inputs on first use across working-directory or Windows drive changes. ([#33](https://github.com/php-fast-forward/config/pull/33))
- Follow current file and directory symlinks, including external retargeting, literal POSIX targets containing URI syntax, and dangling chains or ancestor directories, preserving intermediate links and rejecting cyclic or unresolvable chains before replacement. ([#33](https://github.com/php-fast-forward/config/pull/33))
- Preserve existing POSIX file groups and permission bits during atomic persistence, rejecting replacements with InvalidArgumentException when required metadata cannot be retained or its functions are disabled. ([#33](https://github.com/php-fast-forward/config/pull/33))
- Report unavailable PHP functions required for file-path resolution as InvalidArgumentException before changing persistent configuration. ([#33](https://github.com/php-fast-forward/config/pull/33))
- Preserve native stream-protocol selection and trailing directory separators, preventing URI-like relative paths or malformed file locators from redirecting reads and persistent mutations. ([#33](https://github.com/php-fast-forward/config/pull/33))
- Retain missing-file read defaults for empty locators or paths containing NUL bytes and reject their persistent mutations before creating directories, locks, or files. ([#33](https://github.com/php-fast-forward/config/pull/33))
- Load PHP configuration at every recursive directory depth in deterministic depth-and-path order, preserve filename-pattern overrides, and include resolved files once without traversing child directory symlinks. ([#37](https://github.com/php-fast-forward/config/pull/37))
- Keep accessible configuration available when a child directory is unreadable or disappears during recursive discovery, while preserving root read failures and unrelated errors. ([#37](https://github.com/php-fast-forward/config/pull/37))
- Treat directory path metacharacters literally during top-level and recursive discovery, preventing similarly named sibling directories from contributing configuration. ([#37](https://github.com/php-fast-forward/config/pull/37))
- Include discovered configuration through resolved absolute paths so relative roots cannot load outside namesakes through include_path or changes to the working directory. ([#37](https://github.com/php-fast-forward/config/pull/37))
- Resolve valid source data on cache misses, evictions, non-array entries, and declined initial writes without a has()/get() race or mandatory cache readback, while preserving lazy cache hits. ([#31](https://github.com/php-fast-forward/config/pull/31))
- Report failed persistent cache mutations with RuntimeException when the backend returns false, retaining the local mutation for application recovery. ([#31](https://github.com/php-fast-forward/config/pull/31))
- Resolve PSR-11 container identifiers consistently, require the documented config. prefix, and report empty or unknown configuration identifiers as container not-found errors. ([#30](https://github.com/php-fast-forward/config/pull/30))
- Use core string functions for directory paths and container prefixes so runtime-only PHP 8.3 consumers do not require undeclared multibyte functions or PHP 8.4 polyfills. ([#30](https://github.com/php-fast-forward/config/pull/30))
- Document and test the explicit Laminas config_cache_enabled flag, writable parent requirements, and existing-file reuse for provider and directory file-cache examples. ([#36](https://github.com/php-fast-forward/config/pull/36))

## [1.6.0] - 2026-10-16

### Changed

- Replace dflydev/dot-access-data with native array storage, path traversal, normalization, and merging while preserving configuration lookup, assignment, and aggregation behavior. Empty lookup paths now raise FastForward\Config\Exception\InvalidArgumentException instead of the removed dependency's exception. ([#17](https://github.com/php-fast-forward/config/pull/17))

### Fixed

- Restore Wiki API documentation generation and main-branch publication, preserve Wiki history and unrelated pages, normalize internal page links, and verify published content. ([#13](https://github.com/php-fast-forward/config/pull/13))

## [1.5.1] - 2026-10-09

### Added

- Add a contextual repository mascot banner to the README. ([#4](https://github.com/php-fast-forward/config/pull/4))

### Fixed

- Restore Reports generation and publish documentation, coverage, and metrics through native GitHub Pages artifact deployment, with site verification after deployment and job-scoped permissions. ([#9](https://github.com/php-fast-forward/config/pull/9), [#10](https://github.com/php-fast-forward/config/pull/10), [#11](https://github.com/php-fast-forward/config/pull/11))
- Concatenate sequential lists when aggregating configuration sources, preserving order and duplicates, recursive map merging, later scalar precedence, dot notation, and lazy loading without changing explicit ArrayConfig::set() index-replacement semantics. ([#7](https://github.com/php-fast-forward/config/pull/7))
- Mirror verified Dependabot push test outcomes through a separate completion workflow so required statuses are available with the bot's restricted token. ([#5](https://github.com/php-fast-forward/config/pull/5))
- Allow the isolated CI status publisher to read workflow job outcomes without granting write permissions to test jobs. ([#5](https://github.com/php-fast-forward/config/pull/5))

## [1.5.0] - 2026-09-17

### Added

- Add PhpFileConfig with optional persistence and configFile() helper. ([#2](https://github.com/php-fast-forward/config/pull/2))
- Add comprehensive Sphinx documentation under docs/.

### Changed

- Raise minimum PHP version requirement to ^8.3.
- Implement atomic file persistence using brick/varexporter and temporary files in PhpFileConfig. ([#2](https://github.com/php-fast-forward/config/pull/2))
- Restrict automatic PHP file detection in config() helper to files ending with .php. ([#2](https://github.com/php-fast-forward/config/pull/2))

### Deprecated

- Deprecate LamiasConfigAggregatorConfig in favor of LaminasConfigAggregatorConfig. ([#3](https://github.com/php-fast-forward/config/pull/3))

### Fixed

- Allow setting falsy values (false, 0, '0', '', null) with string keys in ArrayConfig::set(). ([#2](https://github.com/php-fast-forward/config/pull/2))
- Synchronize multi-instance persistent file mutations via sidecar process locking and fresh file reloads in PhpFileConfig. ([#2](https://github.com/php-fast-forward/config/pull/2))
- Correct Sphinx section underline markup for LamiasConfigAggregatorConfig in docs/api-interfaces.rst. ([#3](https://github.com/php-fast-forward/config/pull/3))
- Preserve restrictive file permissions when atomically persisting configuration. ([#2](https://github.com/php-fast-forward/config/pull/2))
- Preserve symlink targets and update real file target during persistent writes. ([#2](https://github.com/php-fast-forward/config/pull/2))
- Verify complete byte count on temporary file write to prevent partial file corruption. ([#2](https://github.com/php-fast-forward/config/pull/2))
- Rename typo LamiasConfigAggregatorConfig to LaminasConfigAggregatorConfig while retaining LamiasConfigAggregatorConfig as a deprecated compatibility alias. ([#3](https://github.com/php-fast-forward/config/pull/3))

## [1.4.0] - 2025-06-11

### Added

- Implement ArrayAccess in ConfigInterface via ArrayAccessConfigTrait. ([#1](https://github.com/php-fast-forward/config/pull/1))
- Add remove() method to ConfigInterface for removing configuration keys. ([#1](https://github.com/php-fast-forward/config/pull/1))

## [1.3.0] - 2025-05-24

### Added

- Introduce ConfigHelper with isAssoc(), normalize(), and flatten() utilities.

### Changed

- Update getIterator() across config classes to yield flattened dot-notated key-value pairs.

## [1.2.0] - 2025-05-24

### Added

- Support custom cache keys and persistent cache updates on set() in CachedConfig.

## [1.1.4] - 2025-04-20

### Fixed

- Require 'config.' prefix for configuration key lookup in ConfigContainer.

## [1.1.3] - 2025-04-20

### Fixed

- Return wrapped configuration instance when resolved by container alias in ConfigContainer.
- Correct package name in installation instructions in README.md.

## [1.1.2] - 2025-04-19

### Fixed

- Rename ContainerNotFoundExceptionInterface to ContainerNotFoundException.

## [1.1.1] - 2025-04-18

### Fixed

- Preserve sequential arrays and non-string keys during array normalization in ArrayConfig.

## [1.1.0] - 2025-04-18

### Added

- Add PSR-11 container implementation via ConfigContainer.

## [1.0.0] - 2025-04-18

### Added

- Initial release of FastForward Config library.
- In-memory configuration storage with dot-notation support via ArrayConfig.
- Directory and recursive directory configuration loaders via DirectoryConfig and RecursiveDirectoryConfig.
- PSR-16 cache wrapper via CachedConfig.
- Laminas ConfigAggregator bridge via LamiasConfigAggregatorConfig.
- Helper functions config(), configCache(), configDir(), and configProvider().
- Support directory path strings directly in config() helper.


[1.7.3]: https://github.com/php-fast-forward/config/compare/v1.7.2...v1.7.3
[1.7.2]: https://github.com/php-fast-forward/config/compare/v1.7.1...v1.7.2
[1.7.1]: https://github.com/php-fast-forward/config/compare/v1.7.0...v1.7.1
[1.7.0]: https://github.com/php-fast-forward/config/compare/v1.6.0...v1.7.0
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
