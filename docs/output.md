# Writing Output

- [Formatting Methods](#formatting-methods)
- [Timestamps](#timestamps)
- [Custom Format Types](#custom-format-types)
- [Access Current Timestamp](#access-current-timestamp)

## Formatting Methods

```php
protected function handle(): void
{
    $this->info('Informational message');       // Green formatted
    $this->comment('Comment text');             // Yellow formatted
    $this->question('Question text');           // Black on cyan
    $this->error('Error message');              // White on red
    $this->line('Plain unformatted text');      // No styling
    $this->errorBlock('Header', 'Details');     // Red block
    $this->newLine();                           // Single blank line
    $this->newLine(3);                          // Three blank lines
}
```

## Timestamps

All formatted output includes a timestamp prefix by default. Disable
per-command:

```php
class MyCommand extends Command
{
    protected bool $messageTimeStamp = false;
}
```

## Custom Format Types

```php
$this->formattedLine('info', 'Custom formatted message');
```

## Access Current Timestamp

```php
$now = $this->getCurrentDatetime();              // "2024-03-15 10:30:00"
$date = $this->getCurrentDatetime('Y-m-d');      // "2024-03-15"
```
