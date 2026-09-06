# Useful Traits

Reusable traits for common CLI patterns. Add with `use TraitName;` in your
command class.

- [DateRangeOption](#daterangeoption)
- [DateOption](#dateoption)
- [FileOption](#fileoption)
- [FileDirectory](#filedirectory)
- [DryRunOption](#dryrunoption)
- [RetryableTask](#retryabletask)
- [ConfirmableAction](#confirmableaction)
- [OutputFormat](#outputformat)
- [Environments](#environments)

## DateRangeOption

Adds `--month`, `--from-date`, `--to-date` options with validation.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\DateRangeOption;

class ReportCommand extends Command
{
    use DateRangeOption;

    public static string $name = 'report:sales';
    public static string $description = 'Generate sales report';

    protected function init(): void
    {
        $this->addDateRangeOption();
    }

    protected function handle(): void
    {
        [$fromDate, $toDate] = $this->dateRangeOption();
        // Returns [?DateTimeImmutable, ?DateTimeImmutable]
    }
}
```

```shell
./console report:sales --month=2024-03
./console report:sales --from-date=2024-03-01 --to-date=2024-03-31
```

Both boundaries are midnight on the day given, so the range depends only on the
input and not on the hour the command ran. `--to-date` is therefore the *start*
of that day: to include the whole of it, compare against
`$toDate->modify('+1 day')` or select `< $toDate + 1 day`.

## DateOption

Single `--date` option with validation. Supports multiple formats with
auto-detection. For ranges, use `DateRangeOption`.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\DateOption;

class DailyReport extends Command
{
    use DateOption;

    public static string $name = 'report:daily';
    public static string $description = 'Generate daily report';

    protected function init(): void
    {
        $this->addDateOption();
    }

    protected function handle(): void
    {
        // Default to today when --date is omitted, so the result is never null
        $date = $this->dateOption(defaultToday: true);

        $this->info('Report for: ' . $date->format('d M Y'));
    }
}
```

The four call shapes, and what each returns when `--date` is absent:

| Call                                     | Without `--date`                 |
|------------------------------------------|----------------------------------|
| `dateOption()`                            | `null` — check before using it   |
| `dateOption(required: true)`              | Throws `InvalidArgumentException` |
| `dateOption(defaultToday: true)`          | Today at midnight                |
| `dateOption(format: ['Y-m-d', 'd/m/Y'])`  | `null`; first matching format wins |

Only `defaultToday` and `required` guarantee a value. The plain form returns
`null`, so guard it:

```php
$date = $this->dateOption();

if ($date === null) {
    $this->error('Pass --date=YYYY-MM-DD.');
    return;
}
```

```shell
./console report:daily --date=2024-06-15    # Y-m-d
./console report:daily --date=15/06/2024    # d/m/Y (if format array includes it)
./console report:daily --date=20240615      # Ymd (if format array includes it)
./console report:daily                      # Uses today if defaultToday: true
```

Formats are tried in order — put the most common format first to avoid
ambiguity (e.g., `01/02/2024` is Feb 1st with `d/m/Y` but Jan 2nd with `m/d/Y`).

Fields the format does not name are reset rather than taken from the current
clock, so `--date=2024-06-15` is midnight on that day whatever time the command
runs, and `Y-m` gives the first of the month. A format that does name the time
(`Y-m-d H:i:s`) keeps what was typed. If you want the boundary at the end of
the day, take it from the parsed value — `$date->modify('+1 day')` — rather
than relying on the hour the command happened to start.

## FileOption

`--file` option supporting single or comma-separated paths.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\FileOption;

class ProcessCommand extends Command
{
    use FileOption;

    public static string $name = 'file:process';
    public static string $description = 'Process files';

    protected function init(): void
    {
        $this->addFileOption();
    }

    protected function handle(): void
    {
        $file = $this->fileOption();                              // string|null
        $files = $this->fileOption('file', null, true);           // array|null
        $file = $this->fileOption('file', null, false, 'csv');    // Appends .csv
        $files = $this->fileOption('file', null, true, 'xlsx');   // Each gets .xlsx
    }
}
```

```shell
./console file:process --file=report.xlsx
./console file:process --file="a.xlsx,b.xlsx,c.xlsx"
```

Empty entries are dropped rather than turned into a filename: `--file=a,,b`
gives two files, and an empty `--file=` gives an empty array (or `null` in
single-file mode) instead of a file named after the extension alone. Passing an
array as `$default` is treated as a comma-separated list.

## FileDirectory

Create directories recursively with error handling.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\FileDirectory;

class SetupCommand extends Command
{
    use FileDirectory;

    public static string $name = 'app:setup';
    public static string $description = 'Setup application directories';

    protected function handle(): void
    {
        $this->mkdir('/path/to/output');                               // Default 0777
        $this->mkdir('/path/to/logs', 0755);                          // Custom mode
        $this->mkdir('/path/to/data', 0777, 'Cannot create: {path}'); // Custom error
    }
}
```

## DryRunOption

`--dry-run` flag to simulate without side effects.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\DryRunOption;

class CleanupCommand extends Command
{
    use DryRunOption;

    public static string $name = 'cache:cleanup';
    public static string $description = 'Delete expired cache files';

    protected function init(): void
    {
        $this->addDryRunOption();
    }

    protected function handle(): void
    {
        $files = glob('/tmp/cache/*.tmp');

        foreach ($files as $file) {
            $this->unlessDryRun("Delete $file", function () use ($file) {
                unlink($file);
                $this->info("Deleted: $file");
            });
        }

        if ($this->isDryRun()) {
            $this->comment('No changes made.');
        }
    }
}
```

```shell
./console cache:cleanup --dry-run
# [DRY RUN] Delete /tmp/cache/abc.tmp
# No changes made.
```

Renaming the flag is enough — `isDryRun()` and `unlessDryRun()` both use the
name you registered:

```php
protected function init(): void
{
    $this->addDryRunOption(name: 'simulate');
}

protected function handle(): void
{
    $this->unlessDryRun('Delete file', fn() => unlink($file));  // reads --simulate
}
```

Pass a name explicitly — `isDryRun('simulate')`, or the third argument to
`unlessDryRun()` — only when a command registers more than one such flag; the
last one registered is the default. Calling either without registering the
option is a `LogicException` naming the missing option and the fix.

## RetryableTask

Retry flaky operations with configurable attempts, delay, and exponential
backoff.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\RetryableTask;
use Throwable;

class SyncCommand extends Command
{
    use RetryableTask;

    public static string $name = 'api:sync';
    public static string $description = 'Sync data from external API';

    protected function handle(): void
    {
        $data = $this->retry(
            callback: fn() => file_get_contents('https://api.example.com/data'),
            maxAttempts: 3,
            delayMs: 2000,
            exponentialBackoff: true,
        );

        $this->info('Fetched ' . strlen($data) . ' bytes');
    }
}
```

Custom retry handler:

```php
use Throwable;

$this->retry(
    callback: fn() => $this->apiClient->fetch(),
    maxAttempts: 5,
    delayMs: 1000,
    onRetry: fn(int $attempt, Throwable $ex) => $this->error("Attempt $attempt: {$ex->getMessage()}"),
);
```

`maxAttempts` must be at least 1; lower values throw `InvalidArgumentException`.
A callback is always run at least once, and the last exception is rethrown when
every attempt fails.

## ConfirmableAction

Environment-aware confirmation guard. Unlike `$this->confirm()`:

- Adds `--force` flag that bypasses prompts (for CI/CD)
- Only prompts in production — auto-proceeds in dev/staging
- Fails safely in non-interactive mode

The environment comes from `APP_ENV`, and **an unset `APP_ENV` counts as
production**: the guard asks rather than assuming a machine it has not been
told about is safe. Set it in your shell or process manager to get the
auto-proceed behaviour:

```shell
export APP_ENV=development
```

See [Environments](#environments) for where the value is read.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\ConfirmableAction;

class MigrateCommand extends Command
{
    use ConfirmableAction;

    public static string $name = 'db:migrate';
    public static string $description = 'Run database migrations';

    protected function init(): void
    {
        $this->addForceOption();
    }

    protected function handle(): void
    {
        if (!$this->confirmToProceed('This will modify the database.')) {
            return;
        }

        $this->info('Running migrations...');
    }
}
```

```shell
./console db:migrate --force              # Any environment: bypasses the prompt
APP_ENV=development ./console db:migrate  # Proceeds without asking
APP_ENV=production ./console db:migrate   # Prompts; cancels if declined
./console db:migrate                      # APP_ENV unset — treated as production
```

Under `--no-interaction` (cron, CI) the prompt cannot be answered, so a
production run without `--force` is **cancelled** rather than proceeding. This
is deliberate: unattended destructive commands should require `--force`
explicitly. `confirmToProceed()` returns `false` there, so return early on it
as the example above does.

An explicit second argument overrides detection entirely, which is useful in
tests: `confirmToProceed('...', env: 'development')`.

## OutputFormat

Switch between table, JSON, and CSV output with `--format`.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\OutputFormat;

class UsersCommand extends Command
{
    use OutputFormat;

    public static string $name = 'users:list';
    public static string $description = 'List all users';

    protected function init(): void
    {
        $this->addFormatOption();
    }

    protected function handle(): void
    {
        $users = [
            ['Alice', 'alice@example.com', 'admin'],
            ['Bob', 'bob@example.com', 'user'],
        ];

        $this->outputFormatted(['Name', 'Email', 'Role'], $users);
    }
}
```

```shell
./console users:list                  # Table (default)
./console users:list --format=json    # JSON array
./console users:list --format=csv     # CSV with headers
```

Rows may be lists or associative arrays. A list is keyed by the headers you
passed; an associative row is written through as-is, so its own keys win.

Each row must be an array, and only `table`, `json`, and `csv` are accepted —
an unrecognised format is an error rather than a silent fall back to the table,
so a typo in a pipeline fails instead of feeding it the wrong shape.

`--format=json` fails if the data cannot be encoded. The usual cause is a
string that is not valid UTF-8, such as a database column stored in another
encoding; convert it with `mb_convert_encoding()` before passing it in.

## Environments

Two features branch on the environment, and both read it the same way:
`getenv('APP_ENV')`, falling back to `'production'` when it is not set.

| Feature                                  | Behaviour                                |
|------------------------------------------|------------------------------------------|
| `confirmToProceed()` (ConfirmableAction)  | Prompts in production, proceeds elsewhere |
| `Schedule::environments()`                | Task runs only in the environments listed |

This package does not read `.env` files — nothing here loads one. Set the
variable in the environment itself:

```shell
# Shell, or a systemd unit / Docker env / CI secret
export APP_ENV=development
```

Or from the entry script, before the application is built, if you already load
configuration another way:

```php
putenv('APP_ENV=' . ($config['env'] ?? 'production'));
```

Under cron the shell profile is usually not read, so `APP_ENV` is unset unless
the crontab sets it — which is exactly when defaulting to production matters:

```
APP_ENV=production
* * * * * cd /path/to/project && ./console schedule:run >> /dev/null 2>&1
```

`Schedule::environments()` reads the value **when the schedule is registered**,
not when the task runs, so changing `APP_ENV` inside a `withScheduler()`
callback has no effect on entries already registered.
