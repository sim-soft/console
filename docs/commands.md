# Creating Commands

- [Class-Based Commands](#class-based-commands)
- [Closure Commands](#closure-commands)

## Class-Based Commands

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
# Output: Hello, Alice! You are 30 years old.
```
