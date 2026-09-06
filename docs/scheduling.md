# Task Scheduling

Define scheduled tasks and run them via a single cron entry.
Uses [dragonmantank/cron-expression](https://github.com/dragonmantank/cron-expression)
for parsing and Symfony Lock for overlap prevention.

- [Setup](#setup)
- [Cron Entry](#cron-entry)
- [Frequency Methods](#frequency-methods)
- [Options & Hooks](#options--hooks)
- [Conditional Scheduling](#conditional-scheduling)
- [Output Capture](#output-capture)
- [Background Execution](#background-execution)
- [Health Check Pings](#health-check-pings)
- [Maintenance Mode](#maintenance-mode)
- [Built-in Commands](#built-in-commands)

## Setup

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);
require "vendor/autoload.php";

use App\Commands\CacheCleanupCommand;
use App\Commands\DataSyncCommand;
use App\Commands\ReportGenerateCommand;
use Simsoft\Console\Application;
use Simsoft\Console\Scheduler;

$status = Application::make('My App', '1.0')
    ->withCommands([
        DataSyncCommand::class,
        ReportGenerateCommand::class,
        CacheCleanupCommand::class,
    ])
    ->withScheduler(function (Scheduler $scheduler) {
        $scheduler->command('data:sync')
            ->everyFiveMinutes()
            ->description('Sync external data')
            ->withoutOverlapping();

        $scheduler->command('report:generate', ['--format' => 'pdf'])
            ->weeklyOn(1, 9, 0)
            ->timezone('America/New_York')
            ->description('Weekly Monday report');

        $scheduler->command('cache:cleanup')
            ->daily()
            ->before(fn() => error_log('Cleanup starting'))
            ->after(fn(int $code) => error_log("Cleanup done: $code"))
            ->onFailure(fn(\Throwable $ex) => error_log("Cleanup failed: {$ex->getMessage()}"));
    })
    ->run();

exit($status);
```

A scheduled command must also be registered with `withCommands()` — the
scheduler dispatches it by name through the same application, so a name that is
not registered fails at run time rather than at registration.

## Cron Entry

```
* * * * * cd /path/to/project && ./console schedule:run >> /dev/null 2>&1
```

## Frequency Methods

| Method                    | Expression        |
|---------------------------|-------------------|
| `->everyMinute()`         | `* * * * *`       |
| `->everyFiveMinutes()`    | `*/5 * * * *`     |
| `->everyTenMinutes()`     | `*/10 * * * *`    |
| `->everyFifteenMinutes()` | `*/15 * * * *`    |
| `->everyThirtyMinutes()`  | `*/30 * * * *`    |
| `->hourly()`              | `0 * * * *`       |
| `->hourlyAt(15)`          | `15 * * * *`      |
| `->daily()`               | `0 0 * * *`       |
| `->dailyAt(14, 30)`       | `30 14 * * *`     |
| `->twiceDaily(1, 13)`     | `0 1,13 * * *`    |
| `->weekly()`              | `0 0 * * 0`       |
| `->weeklyOn(1, 9, 0)`     | `0 9 * * 1`       |
| `->monthly()`             | `0 0 1 * *`       |
| `->monthlyOn(15, 8)`      | `0 8 15 * *`      |
| `->quarterly()`           | `0 0 1 1-12/3 *`  |
| `->yearly()`              | `0 0 1 1 *`       |
| `->weekdays()`            | `0 0 * * 1-5`     |
| `->weekends()`            | `0 0 * * 0,6`     |
| `->cron('5 4 * * 1')`     | Custom expression |

Arguments are range-checked: minutes `0-59`, hours `0-23`, day of week `0-7`
(both `0` and `7` mean Sunday), and day of month `1-31`. Anything outside those
bounds throws `InvalidArgumentException` when the schedule is registered, rather
than producing a cron expression that never fires.

`cron()` validates its expression the same way, for the same reason: an invalid
expression previously threw while `schedule:run` was working out which tasks
were due, which aborted the run before any task executed. One typo took down the
whole schedule, and the error named a cron field rather than the entry.

## Options & Hooks

| Method                             | Description                                   |
|------------------------------------|-----------------------------------------------|
| `->timezone(string\|DateTimeZone)` | Run in specific timezone                      |
| `->description(string)`            | Label shown in `schedule:list`                |
| `->withoutOverlapping()`           | Skip if previous run still active (file lock) |
| `->runInBackground()`              | Run as background process                     |
| `->when(Closure\|bool)`            | Only run when condition is true               |
| `->skip(Closure\|bool)`            | Skip when condition is true                   |
| `->environments(string\|array)`    | Only run in given `APP_ENV`                   |
| `->between(string, string)`        | Only run within time window (HH:MM)           |
| `->unlessBetween(string, string)`  | Skip within time window                       |
| `->evenInMaintenanceMode()`        | Run even during maintenance                   |
| `->sendOutputTo(string)`           | Write output to file (overwrite)              |
| `->appendOutputTo(string)`         | Append output to file                         |
| `->before(Closure)`                | Callback before task                          |
| `->after(Closure($exitCode))`      | Callback after success                        |
| `->onFailure(Closure($throwable))` | Callback on failure                           |
| `->pingBefore(string $url)`        | GET request before task                       |
| `->thenPing(string $url)`          | GET request after success                     |
| `->pingOnFailure(string $url)`     | GET request on failure                        |

## Conditional Scheduling

```php
$scheduler->command('data:sync')
    ->hourly()
    ->when(fn() => file_exists('/tmp/sync-enabled'))
    ->skip(fn() => date('H') === '03')
    ->environments(['production', 'staging'])
    ->between('09:00', '17:00')
    ->unlessBetween('02:00', '04:00');
```

Conditions accumulate rather than replace each other, so the chain above means
what it reads as: every `when()` must pass, and any `skip()` skips the task.
`environments()`, `between()`, and `unlessBetween()` are built on `when()` and
`skip()`, so they combine with them and with each other.

`between()` and `unlessBetween()` evaluate their window in the schedule's
timezone, so `timezone()` may be called before or after them. A window whose end
is earlier than its start is treated as crossing midnight — `between('22:00',
'06:00')` matches the evening and the small hours, not the daytime in between.

A condition that throws is treated as a failed task: it is reported, counted in
the exit code, and the task it guards does not run. The remaining tasks still
do. Conditions often reach for a database or an API, and an unavailable
dependency should not silently run a task that was meant to be gated — nor stop
every other task in the schedule.

## Output Capture

```php
$scheduler->command('report:generate')->daily()->sendOutputTo('/var/log/report.log');
$scheduler->command('data:import')->hourly()->appendOutputTo('/var/log/import.log');
```

## Background Execution

```php
$scheduler->command('video:process')
    ->everyFiveMinutes()
    ->runInBackground()
    ->appendOutputTo('/var/log/video.log');
```

## Health Check Pings

```php
$scheduler->command('billing:charge')
    ->daily()
    ->pingBefore('https://uptime.example.com/start')
    ->thenPing('https://uptime.example.com/done')
    ->pingOnFailure('https://uptime.example.com/fail');
```

## Maintenance Mode

```php
use Simsoft\Console\Scheduler;

->withScheduler(function (Scheduler $scheduler) {
    $scheduler->maintenanceFile(__DIR__ . '/storage/maintenance.php');

    $scheduler->command('data:sync')->hourly();
    $scheduler->command('health:check')->everyMinute()->evenInMaintenanceMode();
})
```

Activate: `touch storage/maintenance.php` or `export APP_MAINTENANCE=true`

## Built-in Commands

```shell
./console schedule:run    # Run all due tasks
./console schedule:list   # List all registered tasks
```

**Fault isolation:** Each task runs independently. If one fails, the scheduler
calls `onFailure`, pings the failure URL, releases the lock, and continues to
the next task.

A task counts as failed when it exits non-zero — which is what a command does
when an exception escapes `handle()`. `after` runs only on success; `onFailure`
receives the throwable.

**Exit code:** `schedule:run` exits non-zero if any task failed, after running
all of them, so cron and monitoring see the failure:

```shell
./console schedule:run || notify-on-call "scheduled tasks failed"
```
