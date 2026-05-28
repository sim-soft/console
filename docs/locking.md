# Command Locking

Prevent parallel execution using Symfony Lock:

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use Simsoft\Console\Command;

class ImportCommand extends Command
{
    static string $name = 'data:import';
    static string $description = 'Import data from external source';
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
