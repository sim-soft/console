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

## DateRangeOption

Adds `--month`, `--from-date`, `--to-date` options with validation.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\DateRangeOption;

class ReportCommand extends Command
{
    use DateRangeOption;

    static string $name = 'report:sales';
    static string $description = 'Generate sales report';

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
php console report:sales --month=2024-03
php console report:sales --from-date=2024-03-01 --to-date=2024-03-31
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

    static string $name = 'report:daily';
    static string $description = 'Generate daily report';

    protected function init(): void
    {
        $this->addDateOption();
    }

    protected function handle(): void
    {
        // Optional (returns null if not provided)
        $date = $this->dateOption();

        // Required (throws if not provided)
        $date = $this->dateOption(required: true);

        // Default to today if not provided
        $date = $this->dateOption(defaultToday: true);

        // Multiple formats — first match wins
        $date = $this->dateOption(format: ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Ymd']);

        // Required + multi-format
        $date = $this->dateOption(format: ['Y-m-d', 'd/m/Y'], required: true);

        $this->info('Report for: ' . $date->format('d M Y'));
    }
}
```

```shell
php console report:daily --date=2024-06-15    # Y-m-d
php console report:daily --date=15/06/2024    # d/m/Y (if format array includes it)
php console report:daily --date=20240615      # Ymd (if format array includes it)
php console report:daily                      # Uses today if defaultToday: true
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

    static string $name = 'file:process';
    static string $description = 'Process files';

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
php console file:process --file=report.xlsx
php console file:process --file="a.xlsx,b.xlsx,c.xlsx"
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

    static string $name = 'app:setup';
    static string $description = 'Setup application directories';

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

    static string $name = 'cache:cleanup';
    static string $description = 'Delete expired cache files';

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
php console cache:cleanup --dry-run
# [DRY RUN] Delete /tmp/cache/abc.tmp
# No changes made.
```

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

    static string $name = 'api:sync';
    static string $description = 'Sync data from external API';

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

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\ConfirmableAction;

class MigrateCommand extends Command
{
    use ConfirmableAction;

    static string $name = 'db:migrate';
    static string $description = 'Run database migrations';

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
php console db:migrate --force    # Production: bypasses prompt
php console db:migrate            # Development: proceeds without asking
```

## OutputFormat

Switch between table, JSON, and CSV output with `--format`.

```php
use Simsoft\Console\Command;
use Simsoft\Console\Traits\OutputFormat;

class UsersCommand extends Command
{
    use OutputFormat;

    static string $name = 'users:list';
    static string $description = 'List all users';

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
php console users:list                  # Table (default)
php console users:list --format=json    # JSON array
php console users:list --format=csv     # CSV with headers
```

Rows may be lists or associative arrays. A list is keyed by the headers you
passed; an associative row is written through as-is, so its own keys win.

Each row must be an array, and only `table`, `json`, and `csv` are accepted —
an unrecognised format is an error rather than a silent fall back to the table,
so a typo in a pipeline fails instead of feeding it the wrong shape.

`--format=json` fails if the data cannot be encoded. The usual cause is a
string that is not valid UTF-8, such as a database column stored in another
encoding; convert it with `mb_convert_encoding()` before passing it in.
