# Changelog

All notable changes to `laravel-recordables` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-10-18

### Fixed

- `recordIfChanged()` no longer records a new snapshot when nothing changed on models whose recordable attributes are cast (backed enums, dates, value objects). The current snapshot is now compared in its stored JSON form, since a cast value never loosely equals its stored string.

### Removed

- Laravel 11 support. Composer refuses to install any Laravel 11 release because of published security advisories, so it can no longer be tested. Projects on Laravel 11 can stay on 0.1.0.

## [0.1.0] - 2026-05-16

### Added

- Initial release: snapshots of Eloquent model data over time with automatic and manual recording, retrieval helpers, analytics (history, growth, delta, min/max, trend, average, chart data), events, a testing fake and pruning.
- Support for Laravel 11, 12 and 13, with Pest 4 allowed so Laravel 13 builds run.

[0.2.0]: https://github.com/BJTheCod3r/laravel-recordables/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/BJTheCod3r/laravel-recordables/releases/tag/0.1.0
