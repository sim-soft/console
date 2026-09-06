# Console Input (Arguments & Options)

- [Defining Input](#defining-input)
- [Methods](#methods)

## Defining Input

Define arguments and options in the `init()` method:

```php
<?php
declare(strict_types=1);

namespace App\Commands;

use Simsoft\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class GreetCommand extends Command
{
    public static string $name = 'greet';
    public static string $description = 'Greet someone';

    protected function init(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Who to greet?')
            ->addArgument('title', InputArgument::OPTIONAL, 'Honorific', 'Mr')
            ->addOption('yell', 'y', InputOption::VALUE_NONE, 'Uppercase output')
            ->addOption('times', 't', InputOption::VALUE_REQUIRED, 'Repeat count', 1);
    }

    protected function handle(): void
    {
        $name = $this->argument('name');
        $title = $this->argument('title');
        $yell = $this->option('yell');
        $times = (int) $this->option('times');

        $message = "Hello, $title $name!";
        if ($yell) {
            $message = strtoupper($message);
        }

        for ($i = 0; $i < $times; $i++) {
            $this->info($message);
        }
    }
}
```

```shell
./console greet Alice --yell --times=3
# [2024-03-15 10:30:00] HELLO, MR ALICE!
# [2024-03-15 10:30:00] HELLO, MR ALICE!
# [2024-03-15 10:30:00] HELLO, MR ALICE!
```

## Methods

| Method                               | Returns | Description                |
|--------------------------------------|---------|----------------------------|
| `$this->argument('name')`            | `mixed` | Get argument value         |
| `$this->argument('name', 'default')` | `mixed` | Get argument with fallback |
| `$this->arguments()`                 | `array` | All arguments              |
| `$this->option('name')`              | `mixed` | Get option value           |
| `$this->option('name', 'default')`   | `mixed` | Get option with fallback   |
| `$this->options()`                   | `array` | All options                |
