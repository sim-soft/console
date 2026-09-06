# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.0] - 2026-09-07

A major release: the supported Symfony range narrowed, and several documented
behaviours changed in ways that affect working code, not only code that was
already failing.

### Upgrading from 2.x

Read these four before upgrading. The rest of this entry is either a fix to
something that was broken or a new error on input that never worked.

- **Symfony 7.4 is now the floor** (was 7.2). `2.0.0` declared `^7.2` but called
  `Application::addCommand()`, added in 7.4, so installing against 7.2 or 7.3
  resolved cleanly and then failed at run time. If you are pinned below 7.4 you
  must upgrade Symfony with this release
- **Dates parse to midnight.** `DateOption` and `DateRangeOption` previously
  inherited the current time of day, so an inclusive `--to-date` included that
  morning's records at 06:00 and excluded them at 18:00, over the same data.
  This is the change most likely to alter output silently rather than raise an
  error: compare against `$toDate->modify('+1 day')` for an inclusive bound
- **Commands invoked from code run non-interactively.** `Application::call()`
  and scheduled tasks no longer prompt; a prompt takes its default. Previously
  they blocked on stdin forever with the process still looking healthy
- **A lockable command that cannot acquire its lock exits `SUCCESS`** instead of
  waiting for the lock to be released

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
- A command invoked from code can no longer block forever on a prompt.
  `Application::call()` built interactive input, so an unknown command name
  with exactly one close match reached Symfony's `Do you want to run "x"
  instead?` confirmation and waited on stdin — in a container, a supervised
  worker, or CI, where the stream stays open and nothing answers, that never
  returned, with the process still looking healthy to monitoring. Scheduled
  tasks ran interactive for the same reason, so a task that prompts stalled
  cron indefinitely. Both now run non-interactively
- `Command::call()` and `callSilently()` inherit the calling command's
  interactivity instead of resetting it. A command run with `--no-interaction`
  dispatched a sub-command that could still prompt: the flag stopped at the
  first command. At a terminal a sub-command may still ask, so this is
  inherited rather than forced off
- Background scheduled tasks pass array-valued arguments correctly. A
  multi-value option — legal in `ArrayInput` — was interpolated into the shell
  command as the literal string `Array`, behind an "Array to string conversion"
  warning, so the background process ran with an argument nobody wrote. The
  option is now repeated once per value. Booleans pass as `true`/`false` rather
  than `1`/`""`, an empty string being indistinguishable from an omitted value,
  and an argument with no faithful string form is rejected by name instead of
  raising `Object of class X could not be converted to string`
- Background scheduled tasks resolve the entry script when `$_SERVER['argv']`
  is present but not a list, which happens with `register_argc_argv` off. The
  old `$_SERVER['argv'][0] ?? 'console'` fallback covered only the absent case:
  a string there indexed to its first character, launching the task against a
  one-character path
- `Command::choice()` explains itself when input is non-interactive and no
  default was given, instead of failing with "Return value must be of type
  array|string, null returned" — which named the method but not the reason it
  had nothing to return. This is the common shape of the bug: a command written
  against a terminal, later run from cron or a test
- `OutputFormat` reports a failed JSON export instead of printing a blank line.
  `json_encode()` returns false rather than throwing, and `writeln()` cast that
  to an empty string, so `--format=json` over a row containing invalid UTF-8 —
  typically a database column in another encoding — wrote one empty line and
  exited `0`. Anything consuming the output read that as "no rows" rather than
  "the export failed"
- `OutputFormat` rejects an unrecognised `--format` instead of falling back to
  the table. `--format=jsonn` exited `0` having printed a table, so a pipeline
  expecting JSON received box-drawing characters and the typo surfaced as a
  downstream parsing bug
- `OutputFormat` names a row that is not an array, rather than failing with a
  `TypeError` from inside `array_is_list()` or an anonymous closure, neither of
  which identified the offending row. A non-string `--format` is reported the
  same way instead of a `TypeError` from `strtolower()`
- Scheduling conditions accumulate instead of overwriting each other. `when()`
  and `skip()` each kept only the last callback, and `environments()`,
  `between()` and `unlessBetween()` are built on them, so in a chain every
  condition but the last was silently discarded:
  `->environments('production')->between('01:00', '04:00')` ran in every
  environment. Every `when()` must now pass, and any `skip()` skips the task
- `Schedule::cron()` validates its expression when the schedule is registered.
  An invalid expression threw from `isDue()` while `schedule:run` was collecting
  due tasks, which aborted the run before anything executed: one typo in one
  entry stopped every other task, and the error named a cron field rather than
  the schedule that carried it
- A `when()` or `skip()` callback that throws no longer aborts the whole
  scheduled run. Conditions commonly consult a database or an API, and an
  unavailable dependency stopped every remaining task. The failure is now
  isolated like any other: it is reported, counted toward the exit code, and the
  task it guards is not run
- `Command::withProgressBar()` rejects a `Countable` that is not also iterable.
  Its signature accepts one, but the body could not traverse it: the progress
  bar ran to 100% while the callback was never invoked, reporting a complete
  run over nothing
- `DateOption` and `DateRangeOption` parse dates at midnight instead of
  inheriting the current time of day. `createFromFormat()` fills fields the
  format does not name from the clock, so `--date=2024-06-15` carried whatever
  time the command started and a `Y-m` format took today's day of month as
  well. Any comparison against a timestamp then depended on when the command
  ran: a report bounded by `--to-date` excluded that morning's records at 06:00
  and included them at 18:00, over the same data. `DateOption` also disagreed
  with itself, since `defaultToday` already returned midnight. A format that
  names the time still keeps it, and out-of-range values are still rejected
- `FileOption` drops empty entries instead of turning them into a filename. The
  extension was appended unconditionally and the `array_filter()` that followed
  could not remove the result, because `".xlsx"` is not empty: `--file=a,,b`
  yielded a phantom `.xlsx` between the two real files, and `--file=` yielded a
  list containing nothing but one. A non-string value is now reported by name
  rather than raising a `TypeError` from `trim()` inside the trait, and an
  array — a documented shape for `$default` — is read as a comma-separated list
  instead of failing the same way
- `DryRunOption` works with a renamed flag. `isDryRun()` defaulted to the
  literal `'dry-run'` regardless of what `addDryRunOption()` had registered, so
  `addDryRunOption(name: 'simulate')` combined with `unlessDryRun()` — which
  calls `isDryRun()` with no argument — threw `The "dry-run" option does not
  exist`, naming an option the command did not have and sending people hunting
  for a typo they had not made. The registered name is now remembered and used
  by default; an explicit name still overrides it. Using either method without
  registering the option raises a `LogicException` that names the option and
  the call that was missing, rather than only reporting its absence

### Added

- `Application::shareGlobally()` shares a configured instance — container,
  scheduler, and all — with the static `call()` API
- `Application::flushGlobal()` drops the shared and auto-built instances, for
  tests and long-running workers where static state would otherwise leak
- `Application::programmaticInput()` builds non-interactive input for a command
  invoked from code, for callers dispatching commands by hand
- CI now runs PHPStan and PHPMD alongside the test suite, in a separate job, so
  the pipeline covers everything `composer check` runs locally
- Test coverage for `ScheduleRunCommand` and `ScheduleListCommand`, which had
  none — hook dispatch, failure reporting, fault isolation, output capture, and
  listing
- CI measures line coverage with pcov and fails below a 94% floor. PHPUnit
  reports coverage but cannot fail on it, so `tools/coverage-threshold.php`
  reads the Clover report and exits non-zero under the floor; the report is
  uploaded as a build artifact. `composer coverage` runs the same check
  locally
- Coverage for `tree()`, `createProgressIndicator()` and `secret()`, three
  documented methods that no test exercised, and for the stderr rendering
  contract of `Application::call()`
- CI runs the matrix against PHP 8.5 and, on every cell, against the lowest
  versions each constraint allows. `composer install` resolves to the highest
  match, so a range's lower bound is a promise nobody was testing — which is how
  the false `^7.2` claim survived. A `--prefer-lowest` leg fails in CI instead
  of in an application

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
- Static analysis runs at PHPStan level 9 — the maximum — rather than 6, so
  nullable types and `mixed` are both checked. The findings were missing
  validation rather than missing annotations, and were fixed as such; see the
  guards listed under Fixed
- **Behavior:** commands invoked through `Application::call()`, and scheduled
  tasks, run non-interactively. A command that prompts through them now takes
  its default instead of asking. This is the only behavior a caller with no
  terminal could rely on: previously it hung
- **Behavior:** `outputFormatted()` throws on an unknown `--format`, on a row
  that is not an array, and on data JSON cannot encode. All three previously
  produced output and exit code `0`
- **Behavior:** `Schedule::cron()` throws `InvalidArgumentException` for an
  invalid expression. Registration that previously succeeded and failed later
  at run time now fails immediately
- **Behavior:** dates parsed by `DateOption` and `DateRangeOption` are midnight
  on the day given unless the format names a time. Code that relied on the
  boundary carrying the current time — usually an inclusive `--to-date` that
  worked only because reports ran in the evening — should compare against
  `$toDate->modify('+1 day')` instead
- **Behavior:** `isDryRun()` and `unlessDryRun()` default to the name passed to
  `addDryRunOption()` rather than the literal `'dry-run'`. A command that
  registered one name and queried another — previously an error — now reads the
  registered flag. `unlessDryRun()` takes an optional third argument for
  commands carrying more than one such flag
- **Behavior:** `fileOption()` returns `null` for an empty single-file value
  and omits empty entries from the multiple-file list, rather than returning a
  name consisting of the extension alone
- **Behavior:** `choice()` declares `$defaultIndex` as
  `bool|float|int|string|null` rather than `mixed`. Symfony's `ChoiceQuestion`
  has always rejected anything else, so this only moves the error to the call
  site — no working call changes
- **Dependencies:** `symfony/console` and `symfony/lock` require
  `^7.4 || ^8.0`, replacing `^7.2`. The code calls `Application::addCommand()`,
  which Symfony added in 7.4; the old constraint installed without complaint and
  then failed at run time. Symfony 7.4 itself requires PHP 8.2, so the existing
  PHP floor is unchanged
- The distributed package contains only what applications load. `.gitattributes`
  had no `export-ignore` rules, so `composer require` fetched the test suite,
  the documentation site, CI workflows and editor files — 101 files where 16 are
  reachable from the autoloader. Installs drop from 560 KB to 150 KB; the
  sources, licence, changelog and README are unaffected

### Documentation

- Examples that could not run are corrected. The container and logging setups
  fataled on `DI\Container` — PHP-DI is a separate install and was neither
  required nor mentioned; two `withCommands([...])` blocks were a literal
  parse error; and the `DateOption` example called `format()` on a value the
  documented call returns as `null`
- `ConfirmableAction` no longer documents the opposite of what it does. An
  unset `APP_ENV` counts as production, so the guard prompts rather than
  auto-proceeding
- New `Environments` section in the traits guide covers where `APP_ENV` is
  read from, the production fallback, that no `.env` file is loaded, and that
  `Schedule::environments()` reads the value at registration rather than at
  run time
- `withDefaultCommand()` and the `$lazyLoad` argument to `withCommands()` are
  documented
- Sample output includes the timestamp prefix the default formatter actually
  writes, and command property declarations are `public static` throughout
- Entry scripts carry a `#!/usr/bin/env php` shebang and the quickstart adds
  the `chmod +x console` step that makes it effective; shell examples invoke
  `./console`, with the `php console` fallback noted for Windows
- The progress indicator example no longer loops on `$this->isStillWorking()`,
  a method that does not exist. It sat inside an otherwise complete block, so
  copying it produced a fatal; it now reads a file, which is the case an
  indeterminate indicator is for
- The README reports the output the default formatter actually writes —
  messages are timestamped, so the hello-world example prints
  `[2024-03-15 10:30:00] Hello World` — and declares command properties
  `public static`, matching every other example and the base class
- Packagist version, PHP version, build status and PHPStan level badges on the
  README and the documentation landing page

### Internal

- The timezone tests no longer skip themselves. Five tests in
  `ScheduleHardeningTest` built their windows relative to "now" and skipped when
  the result crossed midnight, so which of them ran depended on the hour the
  suite started and the timezone handling in `between()`/`unlessBetween()` went
  unverified for part of every day. They now select a timezone that leaves room
  for the window — and, where the point is timezone-awareness, one whose window
  excludes the server's own time, without which a timezone-blind implementation
  passes by coincidence

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
