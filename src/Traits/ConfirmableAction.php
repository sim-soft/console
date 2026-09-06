<?php

namespace Simsoft\Console\Traits;

use Symfony\Component\Console\Input\InputOption;

/**
 * Trait ConfirmableAction
 *
 * Requires confirmation before running destructive actions.
 * In production, requires --force flag unless user confirms interactively.
 *
 * @method static addOption(string $name, string|array<int, string>|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array<int|string, string>|\Closure $suggestedValues = [])
 * @method mixed option(string $name, mixed $default = null)
 * @method bool confirm(string $question, bool $default = false)
 * @method void error(string $message)
 */
trait ConfirmableAction
{
    /**
     * Add the --force option to the command.
     *
     * @return void
     */
    protected function addForceOption(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation without confirmation');
    }

    /**
     * Confirm before proceeding. Returns false if the user declines.
     *
     * Behavior:
     * - If --force is passed, always proceeds.
     * - If APP_ENV is 'production' and no --force, prompts for confirmation.
     * - In other environments, proceeds without prompting.
     *
     * @param string $message Confirmation message.
     * @param string|null $env Override environment detection (defaults to APP_ENV).
     * @return bool True if confirmed, false if declined.
     */
    protected function confirmToProceed(
        string  $message = 'Are you sure you want to run this command?',
        ?string $env = null,
    ): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $environment = $env ?? (getenv('APP_ENV') ?: 'production');

        if ($environment !== 'production') {
            return true;
        }

        if ($this->confirm("$message (y/n)")) {
            return true;
        }

        $this->error('Command cancelled.');
        return false;
    }
}
