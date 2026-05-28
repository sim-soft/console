# Project Structure

```
src/                        # Library source code
├── Application.php         # Main app class (extends Symfony Application)
├── Command.php             # Abstract base command class
├── ClosureCommand.php      # Command built from closures
├── CommandBuilder.php      # Fluent builder for closure commands
└── Traits/                 # Reusable command traits
    ├── DateRangeOption.php # --month, --from-date, --to-date options
    ├── FileOption.php      # --file option handling
    └── FileDirectory.php   # Directory creation helper

example/                    # Example usage
├── console                 # Entry script (class-based commands)
├── closure_console         # Entry script (closure commands)
└── Commands/               # Example command classes

doc/                        # Documentation
└── traits/                 # Trait-specific docs

vendor/                     # Composer dependencies (gitignored)
```

## Architecture Patterns

- **Command pattern**: All commands extend `Simsoft\Console\Command` and
  implement `handle()`
- **Static properties for metadata**: Commands declare `static string $name` and
  `static string $description`
- **`init()` for configuration**: Arguments and options are defined in the
  `init()` method (not the constructor)
- **Traits for reusable options**: Common option patterns are extracted into
  traits under `src/Traits/`
- **Lazy loading by default**: Commands are registered as `LazyCommand`
  instances via `getLazyCommand()`
- **Two registration styles**:
    - Class-based: `Application::make()->withCommands([...])->run()`
    - Closure-based: `Application::command('name', fn() => ...)`

## Naming Conventions

- Command names use colon-separated segments: `namespace:action` (e.g.,
  `example:welcome`)
- Trait files are named after their primary feature (e.g., `FileOption.php`)
- Example command classes use descriptive suffixes: `*Command.php`
