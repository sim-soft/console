# Command Locking

Prevent parallel execution using Symfony Lock:

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use Simsoft\Console\Command;

class ImportCommand extends Command
{
    public static string $name = 'data:import';
    public static string $description = 'Import data from external source';
    protected bool $lockable = true;

    protected function handle(): void
    {
        $this->info('Importing...');
        // If another instance is running, this won't execute
    }
}
```

Set `$lockable = true` — the framework handles lock acquisition and release
automatically.

If the lock is already held by another process, the command does not wait: it
prints a notice and exits with a success status, so a cron entry firing while a
previous run is still going will not be reported as a failure. The lock is
always released when the command finishes, including when `handle()` throws.
