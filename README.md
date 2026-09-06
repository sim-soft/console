# Simsoft Console

A lightweight, Laravel-inspired wrapper for Symfony Console — build PHP CLI
commands with less boilerplate.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Documentation](https://img.shields.io/badge/docs-online-green.svg)](https://sim-soft.github.io/console/)

## Requirements

- PHP 8.2+
- Symfony Console ^7.4 || ^8.0
- Symfony Lock ^7.4 || ^8.0
- dragonmantank/cron-expression ^3.4
- psr/container ^2.0

## Installation

```shell
composer require simsoft/console
```

## Quick Start

```php
#!/usr/bin/env php
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
chmod +x console
./console screen:welcome
# [2024-03-15 10:30:00] Hello World
```

Output is timestamped by default; set `protected bool $messageTimeStamp = false;`
on a command to turn the prefix off. On Windows the shebang is ignored, so run
`php console screen:welcome` instead.

## Documentation

📖 **[Full Documentation](https://sim-soft.github.io/console/)**

| Topic                                        | Description                                                                |
|----------------------------------------------|----------------------------------------------------------------------------|
| [Creating Commands](docs/commands.md)        | Class-based and closure commands                                           |
| [Console Input](docs/input.md)               | Arguments, options, and retrieval methods                                  |
| [Writing Output](docs/output.md)             | Formatted messages, timestamps, newlines                                   |
| [Helpers](docs/helpers.md)                   | Question, table, progress bar, progress indicator, tree                    |
| [Calling Commands](docs/calling-commands.md) | Call from other commands or application code                               |
| [Command Locking](docs/locking.md)           | Prevent parallel execution                                                 |
| [Dependency Injection](docs/container.md)    | PSR-11 container, resolve(), constructor injection                         |
| [Task Scheduling](docs/scheduling.md)        | Cron scheduling, hooks, overlap, output capture, pings                     |
| [Logging](docs/logging.md)                   | PSR-3 logging via DI container                                             |
| [Traits](docs/traits.md)                     | DateRangeOption, DateOption, FileOption, DryRun, Retry, OutputFormat, etc. |

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

## Development

```shell
composer test      # PHPUnit
composer phpstan   # Static analysis, level 9
composer phpmd     # Mess detection
composer check     # All three, as CI runs them
composer coverage  # Coverage report + 94% floor
```

`composer coverage` needs a coverage driver. If `composer test` prints
"No code coverage driver available", install one:

```shell
pecl install pcov          # then enable it in php.ini
```

Or run it in a container without touching your local PHP. The `php` images
carry no composer, so this calls the binaries directly:

```shell
docker run --rm -v "$PWD":/app -w /app php:8.4-cli sh -c \
  'pecl install pcov && docker-php-ext-enable pcov &&
   vendor/bin/phpunit --coverage-clover coverage.xml --coverage-text --only-summary-for-coverage-text &&
   php tools/coverage-threshold.php coverage.xml 94'
```

Match the image tag to the PHP version `vendor/` was installed with, or
Composer's platform check rejects it. On Git Bash, prefix with
`MSYS_NO_PATHCONV=1` so the container paths are not rewritten.

CI enforces the floor on every push, so a drop fails the build rather than
going unnoticed.

## License

MIT — See [LICENSE](LICENSE) for details.
