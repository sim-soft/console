# Calling Commands

- [From Another Command](#from-another-command)
- [From Application Code](#from-application-code)

## From Another Command

```php
protected function handle(): void
{
    $this->call('other:command', [
        'name' => 'Jane',
        '--age' => 18,
    ]);

    $this->callSilently('other:command', [
        'name' => 'John',
        '--age' => 28,
    ]);
}
```

## From Application Code

Register commands globally, then call from controllers/services:

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use App\Commands\ReportCommand;
use App\Commands\SyncCommand;
use Simsoft\Console\Application;

Application::commands([
    SyncCommand::class,
    ReportCommand::class,
]);
```

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use Simsoft\Console\Application;

class ReportController
{
    public function generate(): void
    {
        // Silent (default)
        $exitCode = Application::call('report:generate', [
            'month' => '2024-03',
            '--format' => 'pdf',
        ]);

        // With output
        $exitCode = Application::call('report:generate', [
            'month' => '2024-03',
        ], false);
    }
}
```

### Sharing a Configured Application

`Application::commands()` builds a bare application on first use. It has no
container and no scheduler, so a command calling `$this->resolve()` will fail
under `Application::call()` even though it works under `run()`.

Call `shareGlobally()` to hand your configured instance to the static API:

```php
Application::make('My App', '1.0')
    ->withContainer($container)
    ->withCommands([SyncCommand::class])
    ->shareGlobally();

// Resolves services from $container, same as under run().
$exitCode = Application::call('data:sync');
```

`Application::flushGlobal()` drops the shared instance again — useful in tests
and long-running workers, where leftover static state would leak between runs.

A failed `Application::call()` returns `Command::FAILURE`. When `$silently` is
`false`, the underlying error is rendered to stderr; a silent call stays silent.

## Prompts

`Application::call()` runs the command **non-interactively**, because nothing
is at the keyboard to answer. A prompt inside the command takes its default
rather than asking, and an unknown command name returns `FAILURE` instead of
offering `Do you want to run "x" instead?`. Without this, a call from a
container, a supervised worker, or CI — anywhere stdin stays open — waited on
an answer that never came, while the process still looked healthy.

Scheduled tasks are non-interactive for the same reason, even when you run
`schedule:run` by hand, so a task behaves the same way under cron as it did
when you tested it.

`Command::call()` and `callSilently()` **inherit** the calling command's
interactivity: at a terminal a sub-command can still prompt, and under
`--no-interaction` it cannot. If you need a sub-command to be unconditionally
non-interactive, dispatch it explicitly:

```php
$this->getApplication()->doRun(
    Application::programmaticInput('data:sync'),
    $this->output
);
```
