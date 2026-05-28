<?php

namespace Simsoft\Console;

use Closure;
use Cron\CronExpression;
use DateTimeInterface;
use DateTimeZone;

/**
 * Class Schedule
 *
 * Represents a scheduled task entry.
 */
class Schedule
{
    protected string $expression = '* * * * *';

    protected ?DateTimeZone $timezone = null;

    protected ?string $description = null;

    protected bool $withoutOverlapping = false;

    protected bool $runInBackground = false;

    protected ?Closure $beforeCallback = null;

    protected ?Closure $afterCallback = null;

    protected ?Closure $onFailureCallback = null;

    protected ?Closure $whenCallback = null;

    protected ?Closure $skipCallback = null;

    protected ?string $outputPath = null;

    protected bool $appendOutput = false;

    protected ?string $pingBeforeUrl = null;

    protected ?string $pingAfterUrl = null;

    protected ?string $pingOnFailureUrl = null;

    protected bool $runInMaintenanceMode = false;

    /**
     * Constructor.
     *
     * @param string $commandName The command to schedule.
     * @param array $arguments Command arguments.
     */
    public function __construct(
        protected string $commandName,
        protected array  $arguments = [],
    )
    {
    }

    // ─── Frequency Methods ───────────────────────────────────────────────

    /**
     * Set the cron expression.
     *
     * @param string $expression
     * @return $this
     */
    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): static
    {
        return $this->cron("$minute * * * *");
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(int $hour, int $minute = 0): static
    {
        return $this->cron("$minute $hour * * *");
    }

    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): static
    {
        return $this->cron("0 $firstHour,$secondHour * * *");
    }

    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, int $hour = 0, int $minute = 0): static
    {
        return $this->cron("$minute $hour * * $dayOfWeek");
    }

    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $dayOfMonth, int $hour = 0, int $minute = 0): static
    {
        return $this->cron("$minute $hour $dayOfMonth * *");
    }

    public function quarterly(): static
    {
        return $this->cron('0 0 1 1-12/3 *');
    }

    public function yearly(): static
    {
        return $this->cron('0 0 1 1 *');
    }

    public function weekdays(): static
    {
        return $this->cron('0 0 * * 1-5');
    }

    public function weekends(): static
    {
        return $this->cron('0 0 * * 0,6');
    }

    // ─── Options ─────────────────────────────────────────────────────────

    /**
     * Set the timezone.
     *
     * @param string|DateTimeZone $timezone
     * @return $this
     */
    public function timezone(string|DateTimeZone $timezone): static
    {
        $this->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
        return $this;
    }

    /**
     * Set a description for the scheduled task.
     *
     * @param string $description
     * @return $this
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Prevent overlapping executions using Symfony Lock.
     *
     * @return $this
     */
    public function withoutOverlapping(): static
    {
        $this->withoutOverlapping = true;
        return $this;
    }

    /**
     * Run the task in a background process.
     *
     * @return $this
     */
    public function runInBackground(): static
    {
        $this->runInBackground = true;
        return $this;
    }

    // ─── Conditional Scheduling ──────────────────────────────────────────

    /**
     * Only run when the callback returns true.
     *
     * @param Closure|bool $callback
     * @return $this
     */
    public function when(Closure|bool $callback): static
    {
        $this->whenCallback = is_bool($callback) ? fn() => $callback : $callback;
        return $this;
    }

    /**
     * Skip when the callback returns true.
     *
     * @param Closure|bool $callback
     * @return $this
     */
    public function skip(Closure|bool $callback): static
    {
        $this->skipCallback = is_bool($callback) ? fn() => $callback : $callback;
        return $this;
    }

    /**
     * Only run in the given environment(s).
     *
     * @param string|string[] $environments
     * @return $this
     */
    public function environments(string|array $environments): static
    {
        $environments = (array)$environments;
        $current = getenv('APP_ENV') ?: 'production';
        return $this->when(fn() => in_array($current, $environments, true));
    }

    /**
     * Only run between the given times (24h format HH:MM).
     *
     * @param string $startTime e.g. '09:00'
     * @param string $endTime e.g. '17:00'
     * @return $this
     */
    public function between(string $startTime, string $endTime): static
    {
        return $this->when(function () use ($startTime, $endTime) {
            $now = date('H:i');
            if ($startTime <= $endTime) {
                return $now >= $startTime && $now <= $endTime;
            }
            // Overnight range (e.g. '22:00' to '06:00')
            return $now >= $startTime || $now <= $endTime;
        });
    }

    /**
     * Skip if the current time is between the given times (24h format HH:MM).
     *
     * @param string $startTime e.g. '23:00'
     * @param string $endTime e.g. '04:00'
     * @return $this
     */
    public function unlessBetween(string $startTime, string $endTime): static
    {
        return $this->skip(function () use ($startTime, $endTime) {
            $now = date('H:i');
            if ($startTime <= $endTime) {
                return $now >= $startTime && $now <= $endTime;
            }
            return $now >= $startTime || $now <= $endTime;
        });
    }

    /**
     * Run even when the application is in maintenance mode.
     *
     * Maintenance mode is determined by the existence of a file
     * (default: storage/framework/maintenance.php) or the APP_MAINTENANCE env var.
     *
     * @return $this
     */
    public function evenInMaintenanceMode(): static
    {
        $this->runInMaintenanceMode = true;
        return $this;
    }

    // ─── Output ──────────────────────────────────────────────────────────

    /**
     * Write task output to a file (overwrite).
     *
     * @param string $path
     * @return $this
     */
    public function sendOutputTo(string $path): static
    {
        $this->outputPath = $path;
        $this->appendOutput = false;
        return $this;
    }

    /**
     * Append task output to a file.
     *
     * @param string $path
     * @return $this
     */
    public function appendOutputTo(string $path): static
    {
        $this->outputPath = $path;
        $this->appendOutput = true;
        return $this;
    }

    // ─── Lifecycle Hooks ─────────────────────────────────────────────────

    /**
     * Register a callback to run before the task executes.
     *
     * @param Closure $callback
     * @return $this
     */
    public function before(Closure $callback): static
    {
        $this->beforeCallback = $callback;
        return $this;
    }

    /**
     * Register a callback to run after the task executes successfully.
     *
     * @param Closure $callback Receives the exit code as argument.
     * @return $this
     */
    public function after(Closure $callback): static
    {
        $this->afterCallback = $callback;
        return $this;
    }

    /**
     * Register a callback to run when the task fails.
     *
     * @param Closure $callback Receives the Throwable as argument.
     * @return $this
     */
    public function onFailure(Closure $callback): static
    {
        $this->onFailureCallback = $callback;
        return $this;
    }

    // ─── Ping / Webhook ──────────────────────────────────────────────────

    /**
     * Ping a URL before the task runs.
     *
     * @param string $url
     * @return $this
     */
    public function pingBefore(string $url): static
    {
        $this->pingBeforeUrl = $url;
        return $this;
    }

    /**
     * Ping a URL after the task completes successfully.
     *
     * @param string $url
     * @return $this
     */
    public function thenPing(string $url): static
    {
        $this->pingAfterUrl = $url;
        return $this;
    }

    /**
     * Ping a URL when the task fails.
     *
     * @param string $url
     * @return $this
     */
    public function pingOnFailure(string $url): static
    {
        $this->pingOnFailureUrl = $url;
        return $this;
    }

    // ─── Evaluation ──────────────────────────────────────────────────────

    /**
     * Check if the task is due to run.
     *
     * @param DateTimeInterface|string $currentTime
     * @return bool
     */
    public function isDue(DateTimeInterface|string $currentTime = 'now'): bool
    {
        $cron = new CronExpression($this->expression);
        return $cron->isDue($currentTime, $this->timezone?->getName());
    }

    /**
     * Check if the task should be skipped based on when/skip conditions.
     *
     * @return bool True if the task should be skipped.
     */
    public function shouldSkip(): bool
    {
        if ($this->whenCallback && !($this->whenCallback)()) {
            return true;
        }

        if ($this->skipCallback && ($this->skipCallback)()) {
            return true;
        }

        return false;
    }

    // ─── Getters ─────────────────────────────────────────────────────────

    public function getCommandName(): string
    {
        return $this->commandName;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isWithoutOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    public function isRunInBackground(): bool
    {
        return $this->runInBackground;
    }

    public function getOutputPath(): ?string
    {
        return $this->outputPath;
    }

    public function isAppendOutput(): bool
    {
        return $this->appendOutput;
    }

    public function getBeforeCallback(): ?Closure
    {
        return $this->beforeCallback;
    }

    public function getAfterCallback(): ?Closure
    {
        return $this->afterCallback;
    }

    public function getOnFailureCallback(): ?Closure
    {
        return $this->onFailureCallback;
    }

    public function getPingBeforeUrl(): ?string
    {
        return $this->pingBeforeUrl;
    }

    public function getPingAfterUrl(): ?string
    {
        return $this->pingAfterUrl;
    }

    public function getPingOnFailureUrl(): ?string
    {
        return $this->pingOnFailureUrl;
    }

    public function isRunInMaintenanceMode(): bool
    {
        return $this->runInMaintenanceMode;
    }
}
