# Helpers

Console helpers for interactive prompts and data display.
See [Symfony Console Helpers](https://symfony.com/doc/current/components/console/helpers/index.html)
for the underlying components.

- [Question](#question)
- [Table](#table)
- [Progress Bar](#progress-bar)
- [Progress Indicator](#progress-indicator)
- [Tree](#tree)

## Question

```php
protected function handle(): void
{
    $name = $this->ask('What is your name?');
    $password = $this->secret('Enter password:');

    if ($this->confirm('Continue? (y/n)')) {
        $this->info('Proceeding...');
    }

    $color = $this->choice('Pick a color:', ['Red', 'Green', 'Blue']);
}
```

| Method    | Signature                                                                                                                                                                                    | Returns                          |
|-----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------|
| `ask`     | `ask(string $question, bool\|float\|int\|string\|null $default = null)`                                                                                                                      | `bool\|float\|int\|string\|null` |
| `secret`  | `secret(string $question, bool\|float\|int\|string\|null $default = null)`                                                                                                                   | `bool\|float\|int\|string\|null` |
| `confirm` | `confirm(string $question, bool $default = false)`                                                                                                                                           | `bool`                           |
| `choice`  | `choice(string $question, array $choices, bool\|float\|int\|string\|null $defaultIndex = null, bool $allowMultipleSelections = false, ?int $maxAttempt = null, string $prompt = ' > ', string $errorMessage = '...')` | `string\|array`                  |

`$defaultIndex` is a key of `$choices`, not a value.

**Under `--no-interaction`** — cron, CI, a test — a prompt cannot be answered,
so `ask`, `secret`, and `choice` return their default. `confirm` returns its
default too, which is `false` unless you pass otherwise. `choice` has nothing
to return when no default was given, and throws rather than failing later on
its return type; pass `$defaultIndex`, or guard the prompt:

```php
if ($this->input->isInteractive()) {
    $color = $this->choice('Pick a color:', ['Red', 'Green', 'Blue']);
}
```

## Table

```php
$this->table(
    ['Place', 'Name', 'Score'],
    [
        [1, 'Alice', '100'],
        [2, 'Bob', '95'],
    ]
);
```

With row transformer:

```php
$this->table(
    ['Name', 'Upper'],
    [['alice'], ['bob']],
    fn(array $row) => [$row[0], strtoupper($row[0])]
);
```

## Progress Bar

For tasks with a known number of steps:

```php
$items = range(1, 100);

$this->withProgressBar($items, function ($item, $key) {
    usleep(50000);
});

$this->newLine();
$this->info('Done!');
```

`$items` must be traversable — an array or a `Traversable`. A `Countable` that
is not also iterable throws `InvalidArgumentException`, since there is nothing
to walk.

Custom progress bar:

```php
$bar = $this->createProgressBar(20);
$bar->setBarCharacter('=');
$bar->setProgressCharacter('>');
$bar->setEmptyBarCharacter(' ');
$bar->setBarWidth(50);

$bar->start();
for ($i = 0; $i < 20; $i++) {
    $bar->advance();
}
$bar->finish();
```

## Progress Indicator

For tasks with an unknown duration (indeterminate progress) — streaming a file,
draining a queue, paging an API. Use `createProgressBar()` instead whenever the
total is known up front, since that can show a percentage.

```php
protected function handle(): void
{
    $indicator = $this->createProgressIndicator();

    $indicator->start('Processing...');

    $handle = fopen('data.log', 'r');

    // The line count is not known until the file has been read, which is
    // exactly when an indicator is the right choice over a progress bar.
    while (($line = fgets($handle)) !== false) {
        // process $line...
        $indicator->advance();
    }

    fclose($handle);

    $indicator->finish('Complete!');
}
```

Custom indicator characters:

```php
$indicator = $this->createProgressIndicator(
    indicatorChangeInterval: 200,
    indicatorValues: ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
);

$indicator->start('Loading...');
// ...
$indicator->setMessage('Still loading...');
// ...
$indicator->finish('Done!');
```

## Tree

Display hierarchical data as a tree structure:

```php
protected function handle(): void
{
    $this->tree('Application', [
        'Commands' => [
            'user:create',
            'user:delete',
            'user:list',
        ],
        'Services' => [
            'AuthService',
            'MailService' => [
                'SmtpDriver',
                'SesDriver',
            ],
        ],
        'Config' => [
            'app.php',
            'database.php',
        ],
    ]);
}
```

Output:

```
Application
├── Commands
│   ├── user:create
│   ├── user:delete
│   └── user:list
├── Services
│   ├── AuthService
│   └── MailService
│       ├── SmtpDriver
│       └── SesDriver
└── Config
    ├── app.php
    └── database.php
```

### Custom Styles

Pass a `TreeStyle` preset as the third argument:

```php
use Symfony\Component\Console\Helper\TreeStyle;

// Available presets
$this->tree('Root', $data, TreeStyle::default());   // ├── └── │
$this->tree('Root', $data, TreeStyle::box());       // ┃╸  ┗╸  ┃
$this->tree('Root', $data, TreeStyle::boxDouble()); // ╠═  ╚═  ║
$this->tree('Root', $data, TreeStyle::compact());   // ├  └  │
$this->tree('Root', $data, TreeStyle::light());     // |-- `-- |
$this->tree('Root', $data, TreeStyle::minimal());   // .  .  .
$this->tree('Root', $data, TreeStyle::rounded());   // ├─  ╰─  │
```

Example with `rounded` style:

```
Application
├─ Commands
│  ├─ user:create
│  ╰─ user:list
╰─ Config
   ╰─ app.php
```
