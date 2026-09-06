# Simsoft Console

> A lightweight, Laravel-inspired wrapper for Symfony Console — build PHP CLI
> commands with less boilerplate.

## Features

- Class-based and closure commands
- PSR-11 dependency injection container
- Full-featured task scheduler (cron, hooks, overlap prevention, pings)
- 8 reusable traits (DateOption, DryRun, Retry, OutputFormat, etc.)
- Command locking, progress bars, tree rendering
- Symfony Console ^7.2 || ^8.0 compatible

## Quick Example

```php
<?php
declare(strict_types=1);
require "vendor/autoload.php";

use App\Commands\HelloWorldCommand;
use Simsoft\Console\Application;

$status = Application::make('My App', '1.0')
    ->withCommands([
        HelloWorldCommand::class,
    ])
    ->run();

exit($status);
```

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use Simsoft\Console\Command;

class HelloWorldCommand extends Command
{
    public static string $name = 'screen:welcome';
    public static string $description = 'Display a welcome message';

    protected function handle(): void
    {
        $this->info('Hello World');
    }
}
```

```shell
php console screen:welcome
# [2024-03-15 10:30:00] Hello World
```

Output is timestamped by default; set `protected bool $messageTimeStamp = false;`
on a command to turn the prefix off.

## Requirements

- PHP 8.2+
- Symfony Console ^7.2 || ^8.0
- Symfony Lock ^7.2 || ^8.0
- dragonmantank/cron-expression ^3.4
- psr/container ^2.0
