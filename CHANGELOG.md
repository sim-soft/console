# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `Command::choice()` no longer disables input interactivity as a side effect.
  Previously every prompt after a `choice()` call was silently skipped and
  returned its default. The `$maxAttempt` argument is now delegated to Symfony's
  `Question::setMaxAttempts()`, so it retries on *invalid* input instead of
  re-asking the question a fixed number of times
- Command locks are now released when `handle()` throws. Previously a failing
  lockable command leaked its lock for the lifetime of the process
- `$lockable = true` no longer blocks indefinitely when the lock is held by
  another process. It now skips with a notice, as the documentation described
- `Application::run()` returns a failure exit code after an unrecoverable
  error. Previously it returned `0`, reporting success to the shell and to CI

### Changed

- **Behavior:** `choice(..., maxAttempt: N)` now asks once and retries only on
  invalid input, instead of prompting N times and returning the last answer.
  Values below 1 throw `InvalidArgumentException`
- **Behavior:** a lockable command that cannot acquire its lock exits with
  `SUCCESS` rather than waiting for the lock to be freed

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
