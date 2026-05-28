# Logging

Use any PSR-3 logger (Monolog, etc.) via the DI container.

- [Setup](#setup)
- [Use in Commands](#use-in-commands)
- [Use in Scheduler Hooks](#use-in-scheduler-hooks)

## Setup

```shell
composer require monolog/monolog
```

```php
<?php
declare(strict_types=1);
require "vendor/autoload.php";

use DI\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Simsoft\Console\Application;

$container = new Container([
    LoggerInterface::class => function () {
        $logger = new Logger('app');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/app.log'));
        return $logger;
    },
]);

$status = Application::make('My App', '1.0')
    ->withContainer($container)
    ->withCommands([...])
    ->run();

exit($status);
```

## Use in Commands

```php
use Psr\Log\LoggerInterface;

protected function handle(): void
{
    $logger = $this->resolve(LoggerInterface::class);
    $logger->info('Import started');
    // ...
    $logger->info('Import completed', ['records' => 150]);
}
```

## Use in Scheduler Hooks

```php
use Psr\Log\LoggerInterface;
use Simsoft\Console\Scheduler;
use Throwable;

->withScheduler(function (Scheduler $scheduler) use ($container) {
    $scheduler->command('data:import')
        ->daily()
        ->after(fn(int $code) => $container->get(LoggerInterface::class)->info('Done', ['code' => $code]))
        ->onFailure(fn(Throwable $ex) => $container->get(LoggerInterface::class)->error($ex->getMessage()));
})
```
