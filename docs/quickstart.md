# Quick Start

## Installation

```shell
composer require simsoft/console
```

## Entry Script

Create a `console` file in your project root:

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

## Hello World

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

## Run

```shell
php console screen:welcome
# [2024-03-15 10:30:00] Hello World
```

Output is timestamped by default. To drop the prefix, set
`protected bool $messageTimeStamp = false;` on the command — see
[Writing Output](output.md#timestamps).

## Next Steps

- [Creating Commands](commands.md) — closure commands and error handling
- [Console Input](input.md) — arguments and options
- [Writing Output](output.md) — message types, timestamps, tables
- [Useful Traits](traits.md) — dates, files, dry-run, retries, output formats
