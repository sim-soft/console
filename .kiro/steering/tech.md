# Tech Stack

## Language & Runtime

- PHP 8.0+
- No framework dependency (standalone library)

## Dependencies

- `symfony/console` ^7 — Core console abstraction
- `symfony/lock` ^7 — Command locking support

## Build & Package Management

- Composer (PSR-4 autoloading)
- Namespace: `Simsoft\Console\` → `src/`
- Dev namespace: `Example\` → `example/`

## Common Commands

```bash
# Install dependencies
composer install

# Run tests
composer test        # runs: phpunit tests

# Run example commands
php example/console example:welcome
php example/closure_console example:closure:command
```

## Code Style

- PSR-4 autoloading
- 4 spaces indentation for PHP files
- LF line endings
- UTF-8 charset
- `declare(strict_types=1)` in entry scripts
- PHPDoc blocks on all public/protected methods
- Type declarations on parameters and return types (PHP 8 style)
