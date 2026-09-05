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

- The DI container is now reachable from the static `Application::call()` API.
  `call()` built its own bare application, so a command using `resolve()` failed
  there while working under `run()`. Call `shareGlobally()` on the configured
  application to share it. `resolve()` also names this as the likely cause when
  no container is configured
- `Application::commands()` and `Application::command()` no longer register into
  an application that `call()` has already cached. A registration made after the
  first `call()` was silently ignored, and the command reported as undefined
- `Application::getClosureCommandLoader()` can be called more than once. It
  rewrote the static registry in place, so a second call built factories closing
  over a null builder and fataled with `build() on null`
- `Application::run()` and `Application::call()` render the throwable instead of
  returning a bare failure code with no explanation. A silent `call()` stays
  silent
- `Command::getLazyCommand()` reports that a command with required constructor
  arguments cannot be lazy-loaded, and names the alternatives. It previously
  fataled with an `ArgumentCountError` at resolution time, far from the
  registration that caused it
- The scheduler detects failed tasks. `Command::execute()` catches whatever
  escapes `handle()` and converts it to a `FAILURE` exit code, so nothing
  propagated to `ScheduleRunCommand`'s handler: `onFailure` never ran, the
  failure URL was never pinged, `after` fired as though the task had succeeded,
  and `schedule:run` reported success to cron. Failure is now detected from the
  exit code
- Registration and dispatch failures name the actual problem instead of
  surfacing a raw `TypeError` from inside Symfony, or fataling on null:
  `withCommands()` and `withDefaultCommand()` reject a class that is not a
  `Command` (and say which one), a container returning a non-command names the
  id and what came back, and `call()`/`callSilently()` on a command that was
  never registered with an application explains that rather than calling
  `doRun()` on null
- `ScheduleRunCommand` handles `popen()` returning false when a background task
  cannot be launched, instead of passing it to `pclose()` and raising a
  `TypeError` on top of an already failed launch. The launch failure is counted
  and reported like any other task failure, leaving the remaining tasks to run
- `Command::withProgressBar()` rejects a `Countable` that is not also iterable.
  Its signature accepts one, but the body could not traverse it: the progress
  bar ran to 100% while the callback was never invoked, reporting a complete
  run over nothing

### Added

- `Application::shareGlobally()` shares a configured instance — container,
  scheduler, and all — with the static `call()` API
- `Application::flushGlobal()` drops the shared and auto-built instances, for
  tests and long-running workers where static state would otherwise leak
- CI now runs PHPStan and PHPMD alongside the test suite, in a separate job, so
  the pipeline covers everything `composer check` runs locally
- Test coverage for `ScheduleRunCommand` and `ScheduleListCommand`, which had
  none — hook dispatch, failure reporting, fault isolation, output capture, and
  listing

### Changed

- **Behavior:** an exception escaping `handle()` still reports its message at
  default verbosity, and now adds the exception class, origin, and previous
  exceptions at `-v`, plus stack traces at `-vv`. Previously the class, origin,
  and trace were discarded at every verbosity level
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
- **Behavior:** `schedule:run` exits non-zero when any task failed. It still
  runs every due task first — failures stay isolated — but no longer reports
  success to cron when something broke
- `phpstan.neon` no longer suppresses 13 broad error patterns. The underlying
  issues are fixed instead: 19 malformed `@method` tags across the traits used
  `name(): Type` rather than PHPDoc's `Type name()` syntax and were parsed as
  nothing; array and iterable types throughout the public API now declare their
  value types; and `Command::$formatter` is typed `FormatterHelper` rather than
  the base `HelperInterface` it was annotated as. Two narrowly scoped ignores
  remain, each documented in place: `trait.unused` under `src/Traits` (the
  traits are consumed by applications, not by this package) and `new.static` in
  `Command` (guarded by a reflection check PHPStan cannot follow)
- Static analysis runs at PHPStan level 8 rather than 6, so nullable types are
  checked. The findings it surfaced were missing validation rather than missing
  annotations, and were fixed as such — see the guards listed under Fixed

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
