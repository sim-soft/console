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
| `choice`  | `choice(string $question, array $choices, mixed $defaultIndex = null, bool $allowMultipleSelections = false, ?int $maxAttempt = null, string $prompt = ' > ', string $errorMessage = '...')` | `string\|array`                  |

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

For tasks with an unknown duration (indeterminate progress):

```php
protected function handle(): void
{
    $indicator = $this->createProgressIndicator();

    $indicator->start('Processing...');

    while ($this->isStillWorking()) {
        // do work...
        $indicator->advance();
    }

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
