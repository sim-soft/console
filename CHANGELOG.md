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
- `RetryableTask::retry()` no longer fails with `Can only throw objects` when
  `$maxAttempts` is below 1. The loop body was skipped entirely, leaving the
  last exception null. It now rejects the argument up front
- `Command::callSilently()` restores the previous output verbosity. Previously
  it left the output quiet for the rest of the process, silencing every
  subsequent message from the calling command
- `Command::errorBlock()` no longer emits a stray unmatched `]`. The timestamp
  label was built from mismatched fragments when `$messageTimeStamp` was on
- `Command::newLine()` writes plain newlines instead of routing them through the
  timestamping writer, which prefixed blank lines with a timestamp
- `DateRangeOption` rejects out-of-range dates instead of silently rolling them
  over. `--from-date=2026-13-45` became `2027-02-14` and `2023-02-29` became
  `2023-03-01`; both are now reported as invalid. The upper-bound comparison
  also guarded only one of the two dates for null
- `Schedule::between()` and `unlessBetween()` evaluate their window in the
  schedule's timezone rather than the server's, and handle windows that cross
  midnight
- Background scheduled tasks quote the PHP binary, script, and output paths, so
  paths containing spaces no longer break the generated command. The Windows
  branch now tests `PHP_OS_FAMILY` instead of matching `WIN` inside `PHP_OS`

### Changed

- **Behavior:** frequency helpers (`hourlyAt()`, `dailyAt()`, `twiceDaily()`,
  `weeklyOn()`, `monthlyOn()`) validate their arguments and throw
  `InvalidArgumentException` for out-of-range values, instead of building a cron
  expression that never fires
- **Behavior:** `retry()` throws `InvalidArgumentException` when `$maxAttempts`
  is less than 1
- **Behavior:** `DateRangeOption` accepts only well-formed `Y-m-d` values.
  Inputs previously coerced into a date — short forms such as `2024-1-5`,
  trailing content, and out-of-range components — now raise an error

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
