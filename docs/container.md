# Dependency Injection Container

Accepts any PSR-11 container. Commands are resolved from it when available,
otherwise instantiated directly.

- [Setup](#setup)
- [Resolving Services Inside Commands](#resolving-services-inside-commands)
- [Constructor Injection](#constructor-injection)
- [Methods](#methods)

## Setup

```php
<?php
declare(strict_types=1);
require "vendor/autoload.php";

use App\Commands\SimpleCommand;
use App\Commands\SyncCommand;
use App\Services\ApiClient;
use DI\Container;
use Simsoft\Console\Application;

$container = new Container([
    SyncCommand::class => fn() => new SyncCommand(
        new ApiClient('https://api.example.com'),
    ),
]);

$status = Application::make('My App', '1.0')
    ->withContainer($container)
    ->withCommands([
        SyncCommand::class,    // Resolved from container
        SimpleCommand::class,  // Falls back to new SimpleCommand()
    ])
    ->run();

exit($status);
```

## Resolving Services Inside Commands

```php
use App\Services\ApiClient;
use App\Services\Cache;

protected function handle(): void
{
    $api = $this->resolve(ApiClient::class);

    if ($this->hasService(Cache::class)) {
        $this->resolve(Cache::class)->flush();
    }
}
```

## Constructor Injection

Commands resolved from the container can use constructor injection:

```php
use App\Services\ApiClient;

public function __construct(private ApiClient $api)
{
    parent::__construct();
}

protected function handle(): void
{
    $data = $this->api->fetch('/users');
}
```

## Methods

| Method                          | Description                       |
|---------------------------------|-----------------------------------|
| `$this->resolve(string $id)`    | Get service (throws if not found) |
| `$this->hasService(string $id)` | Check if service exists           |
