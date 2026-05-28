# Simsoft Console

A lightweight, Laravel-inspired wrapper for Symfony Console — built PHP CLI
commands with less boilerplate.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Requirements

- PHP 8.2+
- Symfony Console ^7.2 || ^8.0
- Symfony Lock ^7.2 || ^8.0
- dragonmantank/cron-expression ^3.4
- psr/container ^2.0

## Installation

```shell
composer require simsoft/console
```

## Quick Start

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
    static string $name = 'screen:welcome';
    static string $description = 'Display a welcome message';

    protected function handle(): void
    {
        $this->info('Hello World');
    }
}
```

```shell
php console screen:welcome
# Output: Hello World
```

## Documentation

| Topic                                       | Description                                                                |
|---------------------------------------------|----------------------------------------------------------------------------|
| [Creating Commands](doc/commands.md)        | Class-based and closure commands                                           |
| [Console Input](doc/input.md)               | Arguments, options, and retrieval methods                                  |
| [Writing Output](doc/output.md)             | Formatted messages, timestamps, newlines                                   |
| [Helpers](doc/helpers.md)                   | Question, table, progress bar, progress indicator, tree                    |
| [Calling Commands](doc/calling-commands.md) | Call from other commands or application code                               |
| [Command Locking](doc/locking.md)           | Prevent parallel execution                                                 |
| [Dependency Injection](doc/container.md)    | PSR-11 container, resolve(), constructor injection                         |
| [Task Scheduling](doc/scheduling.md)        | Cron scheduling, hooks, overlap, output capture, pings                     |
| [Logging](doc/logging.md)                   | PSR-3 logging via DI container                                             |
| [Traits](doc/traits.md)                     | DateRangeOption, DateOption, FileOption, DryRun, Retry, OutputFormat, etc. |

## API Quick Reference

### Application

| Method                                        | Description              |
|-----------------------------------------------|--------------------------|
| `Application::make($name, $version)`          | Create instance          |
| `->withContainer(ContainerInterface)`         | Set PSR-11 container     |
| `->withCommands(array $classes)`              | Register commands        |
| `->withScheduler(Closure)`                    | Configure scheduler      |
| `->run()`                                     | Run the application      |
| `Application::call($name, $input, $silently)` | Execute programmatically |

### Command

| Method                                                   | Description                    |
|----------------------------------------------------------|--------------------------------|
| `$this->argument($name, $default)`                       | Get argument                   |
| `$this->option($name, $default)`                         | Get option                     |
| `$this->info($msg)` / `error()` / `comment()` / `line()` | Output                         |
| `$this->ask()` / `secret()` / `confirm()` / `choice()`   | Prompts                        |
| `$this->table($headers, $rows)`                          | Render table                   |
| `$this->withProgressBar($data, $callback)`               | Progress bar                   |
| `$this->createProgressBar($max)`                         | Manual progress bar            |
| `$this->createProgressIndicator()`                       | Indeterminate progress spinner |
| `$this->tree($root, $values)`                            | Render tree structure          |
| `$this->call($name, $input)` / `callSilently()`          | Call commands                  |
| `$this->resolve($id)` / `hasService($id)`                | DI container access            |

## Comparison with Alternatives

|                            | **Simsoft Console**               | **Symfony Console** | **Laravel Zero** | **Silly**       |
|----------------------------|-----------------------------------|---------------------|------------------|-----------------|
| **Dependencies**           | 4                                 | 0 (is the dep)      | 30+              | 2               |
| **Install size**           | ~100KB + Symfony                  | ~600KB              | ~15MB+           | ~30KB + Symfony |
| **Command style**          | Class + Closures                  | Class only          | Class            | Closures only   |
| **DI Container**           | ✅ PSR-11                          | ❌                   | ✅                | ✅               |
| **Scheduler**              | ✅ Full-featured                   | ❌                   | ✅                | ❌               |
| **Command locking**        | ✅ Built-in                        | Manual              | ❌                | ❌               |
| **Overlap prevention**     | ✅ Symfony Lock                    | ❌                   | ✅                | ❌               |
| **Conditional scheduling** | ✅ `when()`, `skip()`, `between()` | ❌                   | ✅                | ❌               |
| **Output capture**         | ✅                                 | ❌                   | ✅                | ❌               |
| **Background execution**   | ✅                                 | ❌                   | ✅                | ❌               |
| **Webhook/ping**           | ✅                                 | ❌                   | ✅                | ❌               |
| **Maintenance mode**       | ✅                                 | ❌                   | ✅                | ❌               |
| **Reusable traits**        | ✅ 8 traits                        | ❌                   | ❌                | ❌               |

## License

MIT — See [LICENSE](LICENSE) for details.
