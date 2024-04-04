# Simsoft Console
A console application, built from Symfony/Console

## Install
```shell
composer require simsoft/console
```

## Basic Usage
Setup in bootstrap or entry script file.
###### console.php
```php
<?php
declare(strict_types=1);
require "vendor/autoload.php";

use Simsoft\Console\Application;

$status = Application::make()
    ->withCommands([
        \App\HelloWorldCommand::class,
    ])
    ->run();

exit($status);
```
## Create a Simple Command
Create a simple HelloWorldCommand class.
```php
<?php

namespace App;

use Simsoft\Console\Command;

class HelloWorldCommand extends Command
{
    static string $name = 'screen:welcome';
    static string $description = 'Hi Guest';
    protected bool $lockable = true; // Enable lock. Default: false.

    protected function handle(): void
    {
        $this->info('Hello World');
    }
}
```
## Add Console Arguments and Options
For detail tutorial, please refer to [Symfony Console: Console Input (Arguments & Options)](https://symfony.com/doc/current/console/input.html).
```php
<?php

namespace App;

use Simsoft\Console\Command;

class HelloWorldCommand extends Command
{
    static string $name = 'screen:welcome';
    static string $description = 'Hi Guest';
    protected bool $lockable = true; // Enable lock. Default: false.

    protected function init(): void
    {
        $this
            // Add arguments
            ->addArgument('name', InputArgument::REQUIRED, 'Who do you want to greet?')
            ->addArgument('last_name', InputArgument::OPTIONAL, 'Your last name?')

            // Add option
            ->addOption(
                'iterations',
                'i',
                InputOption::VALUE_REQUIRED,
                'How many times should the message be printed?',
                1
            )
        ;
    }

    protected function handle(): void
    {
        // get arguments and options.
        $name = $this->argument('name');
        $lastName = $this->argument('last_name');
        $iterations = $this->option('iterations');

        $arguments = $this->arguments();
        // $arguments contains ['name' => '..user input.. ', 'last_name' => '...']

        $options = $this->options();
        // $options contains ['iterations' => '..user input.. ']

        for($i = 0; $i < $iterations; ++$i) {
            $this->info('Hi');
        }

        $this->error('Display error message');
    }
}
```
## Closure Command
### Example Usage
```php
<?php
declare(strict_types=1);
require "vendor/autoload.php";

use Simsoft\Console\Application;

Application::command('example:closure:command', function() {
    $this->info('Hello World!');
})

$status = Application::make()->run();
```
Define closure command with description.
```php
Application::command('example:closure:command', function() {
    $this->info('Hello World!');
})->purpose('Simple closure command');
```
Define closure command with inputs.
```php
use Symfony\Component\Console\Input\InputArgument;

Application::command('example:closure:command2', function() {
    $name = $this->argument('name');
    $this->info("I got your name: $name");
})
->purpose('Get user name')
->input(function(){
    $this->addArgument('name', InputArgument::REQUIRED, 'Name required');
});
```
## Writing Output
```php
/**
 * @throws \Throwable
 */
protected function handle(): void
{
    $this->info('Hello World');         // Write info message
    $this->comment('Comment text');
    $this->question('My Question?');
    $this->error('Warning');            // Write error message
    $this->line('Simple line');         // Write simple un-formatted message
    $this->errorBlock('Block Header', 'Block message');
    $this->newLine();                   // Write a single blank line
    $this->newLine(3);                  // Write three blank lines
}
```
## Prompting for Input
```php
/**
 * @throws \Throwable
 */
protected function handle(): void
{
    // Simple question
    // Other argument: $default.
    $name = $this->ask('What is your name?');
    $this->info("Your name is $name");

    // Hide user input question
    // Other argument: $default
    $secret = $this->secret('Please tell me a secret?');
    $this->info("Your secret is $secret");

    // Asking for confirmation.
    // Other argument: $default.
    if ($this->confirm('Are you above 18yo (y/n)?')) {
        $this->info('You have grow up!');
    } else {
        $this->info("You are very young!");
    }

    // Multiple choice questions.
    // Other arguments: $defaultIndex, $maxAttempts, $allowMultipleSelections.
    $myChoice = $this->choice('Which color do you like?', ['Yellow', 'Orange', 'Blue']);
    $this->info("You have selected $myChoice");
}
```
## Table and Progress Bar
### Tables
```php
protected function handle(): void
{
    $this->table(
        ['Place', 'Name', 'Score'],
        [
            [1, 'Albert', '100'],
            [2, 'Jane', '98'],
            [3, 'Alvin', '95'],
            [4, 'Mary', '89'],
            [5, 'Alex', '88'],
            [6, 'Wong', '87'],
        ]
    );
}
```
### Progress Bar
```php
protected function handle(): void
{
    $items = range(1, 10);

    $this->withProgressBar($items, function($item) {
        // perform task on $item.
    });
}
```

Customize Progress Bar
```php
protected function handle(): void
{
    $max = 20; // Maximum items.

    $bar = $this->createProgressBar($max);
    $bar->setBarCharacter('$');
    $bar->setProgressCharacter('>>');
    $bar->setBarWidth(50);
    $bar->setEmptyBarCharacter('_');

    $bar->start();

    for ($i = 1; $i <= $max; ++$i) {
        // do something.

        $bar->advance();
    }
    $bar->finish();
}
```
## Calling Commands From Other Commands
```php
protected function handle(): void
{
    // Call another command.
    $this->call('example:friendly', [
        'name' => 'Jane',
        '--age' => 18,
    ]);

    // Call another commands silently.
    $this->callSilently('example:friendly', [
        'name' => 'John',
        '--age' => 28,
    ]);
}
```
## Calling Commands From Controller
### Register commands in bootstrap index.php
```php
<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Simsoft\Console\Application;

Application::commands([
    \Example\Commands\HelloWorldCommand::class,
    \Example\Commands\FriendlyCommand::class,
    \Example\Commands\QuestionsCommand::class,
    \Example\Commands\WinnersCommand::class,
    \Example\Commands\MoneyComeCommand::class,
]);

Application::command('example:closure:command', function() {
    $this->info('Hello World!');
})->purpose('Simple closure command');

Application::command('example:closure:command2', function() {
    $name = $this->argument('name');
    $this->info("I got your name: $name");

    $age = $this->option('age');
    $this->info("Your age is $age");

})->purpose('Get user name')->input(function(){
    $this->addArgument('name', InputArgument::REQUIRED);
    $this->addOption('age');
});

```
Execute command
```php
<?php

namespace App;

use Simsoft\Console\Application;

class AppController
{
    public function index(): void
    {
        Application::call('example:closure:command2', [
            'name' => 'Marry',
            '--age' => 25,
        ]);
    }
}
```


## License
The Simsoft Validator is licensed under the MIT License. See the [LICENSE](LICENSE) file for details
