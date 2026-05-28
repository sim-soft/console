# Calling Commands

- [From Another Command](#from-another-command)
- [From Application Code](#from-application-code)

## From Another Command

```php
protected function handle(): void
{
    $this->call('other:command', [
        'name' => 'Jane',
        '--age' => 18,
    ]);

    $this->callSilently('other:command', [
        'name' => 'John',
        '--age' => 28,
    ]);
}
```

## From Application Code

Register commands globally, then call from controllers/services:

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use App\Commands\ReportCommand;
use App\Commands\SyncCommand;
use Simsoft\Console\Application;

Application::commands([
    SyncCommand::class,
    ReportCommand::class,
]);
```

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use Simsoft\Console\Application;

class ReportController
{
    public function generate(): void
    {
        // Silent (default)
        $exitCode = Application::call('report:generate', [
            'month' => '2024-03',
            '--format' => 'pdf',
        ]);

        // With output
        $exitCode = Application::call('report:generate', [
            'month' => '2024-03',
        ], false);
    }
}
```
