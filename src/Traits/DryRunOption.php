<?php

namespace Simsoft\Console\Traits;

use Symfony\Component\Console\Input\InputOption;

/**
 * Trait DryRunOption
 *
 * Adds a --dry-run flag that allows commands to simulate actions without side effects.
 *
 * @method static addOption(string $name, string|array<int, string>|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array<int|string, string>|\Closure $suggestedValues = [])
 * @method mixed option(string $name, mixed $default = null)
 * @method void comment(string $message)
 */
trait DryRunOption
{
    /**
     * Add the --dry-run option to the command.
     *
     * @param string $name Option name.
     * @param string $shortcut Option shortcut.
     * @param string $description Option description.
     * @return void
     */
    protected function addDryRunOption(
        string $name = 'dry-run',
        string $shortcut = '',
        string $description = 'Simulate the command without making changes',
    ): void
    {
        $this->addOption($name, $shortcut ?: null, InputOption::VALUE_NONE, $description);
    }

    /**
     * Check if the command is running in dry-run mode.
     *
     * @param string $name Option name.
     * @return bool
     */
    protected function isDryRun(string $name = 'dry-run'): bool
    {
        return (bool)$this->option($name);
    }

    /**
     * Execute a callback only if NOT in dry-run mode.
     * In dry-run mode, outputs a comment describing what would happen.
     *
     * @param string $description What the action would do.
     * @param callable $callback The actual action.
     * @return mixed The callback result, or null if dry-run.
     */
    protected function unlessDryRun(string $description, callable $callback): mixed
    {
        if ($this->isDryRun()) {
            $this->comment("[DRY RUN] $description");
            return null;
        }

        return $callback();
    }
}
