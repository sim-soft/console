<?php

namespace Simsoft\Console\Traits;

use LogicException;
use Symfony\Component\Console\Exception\InvalidArgumentException;
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
     * The name addDryRunOption() registered.
     *
     * Remembered so the rest of the trait can find the option after it has
     * been renamed. isDryRun() previously defaulted to the literal 'dry-run'
     * independently of the registration, so renaming the option broke
     * unlessDryRun() — which calls isDryRun() with no argument — with "The
     * "dry-run" option does not exist".
     */
    private string $dryRunOptionName = 'dry-run';

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
        $this->dryRunOptionName = $name;

        $this->addOption($name, $shortcut ?: null, InputOption::VALUE_NONE, $description);
    }

    /**
     * Check if the command is running in dry-run mode.
     *
     * @param string|null $name Option name. Defaults to the registered name.
     * @return bool
     * @throws LogicException If the named option was never registered.
     */
    protected function isDryRun(?string $name = null): bool
    {
        $name ??= $this->dryRunOptionName;

        try {
            return (bool)$this->option($name);
        } catch (InvalidArgumentException $ex) {
            // Symfony reports the missing option but not why this trait went
            // looking for it, and the message named 'dry-run' even for a
            // command whose flag is called something else — which sent people
            // hunting for a typo they had not made.
            throw new LogicException(sprintf(
                'The "%s" option is not registered, so dry-run mode cannot be determined. '
                . 'Call $this->addDryRunOption(%s) in init(), or pass the name you did '
                . 'register to isDryRun().',
                $name,
                $name === 'dry-run' ? '' : var_export($name, true)
            ), 0, $ex);
        }
    }

    /**
     * Execute a callback only if NOT in dry-run mode.
     * In dry-run mode, outputs a comment describing what would happen.
     *
     * @param string $description What the action would do.
     * @param callable $callback The actual action.
     * @param string|null $name Option name. Defaults to the registered name.
     * @return mixed The callback result, or null if dry-run.
     * @throws LogicException If the named option was never registered.
     */
    protected function unlessDryRun(string $description, callable $callback, ?string $name = null): mixed
    {
        if ($this->isDryRun($name)) {
            $this->comment("[DRY RUN] $description");
            return null;
        }

        return $callback();
    }
}
