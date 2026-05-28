# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-05-28

### Changed

- Minimum PHP version bumped to 8.2
- Symfony dependencies updated to ^7.2
- `ClosureCommand` refactored to use instance properties instead of static
  properties, allowing multiple closure commands to coexist safely
- PHPUnit updated to ^11.5|^12.0

### Fixed

- Lock logic in `Command::execute()` — commands with `$lockable = true` now
  correctly execute when the lock is acquired

### Added

- Unit test suite with 110+ tests covering all core functionality
- GitHub Actions CI workflow for PHP 8.2, 8.3, and 8.4
- `ClosureCommand::setHandler()` method for setting the command callback
- `phpunit.xml` configuration

## [1.0.0] – Initial Release

### Added

- `Application` class wrapping Symfony Console
- `Command` abstract base class with Laravel-inspired API
- `ClosureCommand` and `CommandBuilder` for closure-based commands
- `DateRangeOption`, `FileOption`, and `FileDirectory` traits
- Lazy command loading support
- Command locking via Symfony Lock
