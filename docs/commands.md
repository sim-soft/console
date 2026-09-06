# Creating Commands

- [Class-Based Commands](#class-based-commands)
- [Closure Commands](#closure-commands)
- [Registering Commands](#registering-commands)
- [Default Command](#default-command)
- [Error Handling](#error-handling)

## Class-Based Commands

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

| Property/Method       | Purpose                                 |
|-----------------------|-----------------------------------------|
| `static $name`        | Command name (`namespace:action`)       |
| `static $description` | Short description shown in command list |
| `init()`              | Define arguments and options            |
| `handle()`            | Command logic — called on execution     |

## Closure Commands

```php
<?php
declare(strict_types=1);
require "vendor/autoload.php";

use Simsoft\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

// Simple
Application::command('greet:hello', function () {
    $this->info('Hello World!');
})->purpose('A simple greeting');

// With arguments and options
Application::command('greet:user', function () {
    $name = $this->argument('name');
    $age = $this->option('age');
    $this->info("Hello, $name! You are $age years old.");
})
->purpose('Greet a user by name')
->input(function () {
    $this->addArgument('name', InputArgument::REQUIRED, 'User name');
    $this->addOption('age', 'a', InputOption::VALUE_REQUIRED, 'User age');
});

$status = Application::make()->run();
exit($status);
```

```shell
php console greet:user Alice --age=30
# [2024-03-15 10:30:00] Hello, Alice! You are 30 years old.
```

## Registering Commands

`withCommands()` takes the command classes and, optionally, whether to load
them lazily:

```php
Application::make('My App', '1.0')
    ->withCommands([HelloWorldCommand::class])           // Lazy (default)
    ->withCommands([HelloWorldCommand::class], false)    // Instantiated at registration
    ->run();
```

Lazy loading is on by default: a command is constructed only when it is
actually invoked, so a large command list does not cost anything on an
unrelated run. It needs a constructor callable with no arguments — a command
that requires constructor arguments is reported as such at registration, and
should either be resolved from a [container](container.md) or registered with
`false` here.

## Default Command

Run one command when the script is invoked with no command name:

```php
Application::make('My App', '1.0')
    ->withCommands([HelloWorldCommand::class])
    ->withDefaultCommand(HelloWorldCommand::class)
    ->run();
```

```shell
php console                 # Runs screen:welcome
php console screen:welcome  # Same thing, named explicitly
```

Useful for single-purpose scripts. Without it, a bare `php console` lists the
available commands.

## Error Handling

An exception escaping `handle()` is caught, reported, and turned into a
`FAILURE` exit code. How much is reported depends on verbosity:

| Verbosity  | Output                                                     |
|------------|------------------------------------------------------------|
| default    | The exception message only                                 |
| `-v`       | Adds exception class, file, line, and any previous exceptions |
| `-vv`      | Adds the full stack trace for each                          |

```shell
php console data:sync        # Connection refused
php console data:sync -vv    # ...with class, origin, cause, and trace
```

Ordinary runs stay readable, and the detail needed to debug a failure is one
flag away rather than lost.
