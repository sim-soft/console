<?php

namespace Simsoft\Console;

use Closure;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

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

    /** @var Closure[] All must pass for the task to run. */
    protected array $whenCallbacks = [];

    /** @var Closure[] Any one of these skips the task. */
    protected array $skipCallbacks = [];

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
     * @param array<string, mixed> $arguments Command arguments.
     */
    public function __construct(
        protected string $commandName,
        /** @var array<string, mixed> */
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
        // Validate at the call site. An invalid expression otherwise threw from
        // isDue() while the scheduler was collecting due tasks, which aborted
        // the whole run: one typo in one entry stopped every other task from
        // running, and the error named a cron field rather than the schedule.
        if (!CronExpression::isValidExpression($expression)) {
            throw new InvalidArgumentException(
                "Invalid cron expression for \"$this->commandName\": \"$expression\"."
            );
        }

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
        $this->assertInRange($minute, 0, 59, 'minute');
        return $this->cron("$minute * * * *");
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(int $hour, int $minute = 0): static
    {
        $this->assertInRange($hour, 0, 23, 'hour');
        $this->assertInRange($minute, 0, 59, 'minute');
        return $this->cron("$minute $hour * * *");
    }

    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): static
    {
        $this->assertInRange($firstHour, 0, 23, 'hour');
        $this->assertInRange($secondHour, 0, 23, 'hour');
        return $this->cron("0 $firstHour,$secondHour * * *");
    }

    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, int $hour = 0, int $minute = 0): static
    {
        $this->assertInRange($dayOfWeek, 0, 7, 'day of week');
        $this->assertInRange($hour, 0, 23, 'hour');
        $this->assertInRange($minute, 0, 59, 'minute');
        return $this->cron("$minute $hour * * $dayOfWeek");
    }

    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $dayOfMonth, int $hour = 0, int $minute = 0): static
    {
        $this->assertInRange($dayOfMonth, 1, 31, 'day of month');
        $this->assertInRange($hour, 0, 23, 'hour');
        $this->assertInRange($minute, 0, 59, 'minute');
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

    /**
     * Guard a cron field value, so out-of-range input fails at the call site
     * rather than later inside CronExpression when the task is evaluated.
     *
     * @param int $value The supplied value.
     * @param int $min Lowest accepted value.
     * @param int $max Highest accepted value.
     * @param string $label Field name used in the error message.
     * @return void
     * @throws InvalidArgumentException When the value is out of range.
     */
    protected function assertInRange(int $value, int $min, int $max, string $label): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(
                "Invalid $label: $value. Expected a value between $min and $max."
            );
        }
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
     * Conditions accumulate: every when() must pass. They previously
     * overwrote each other, so in a chain only the last one was consulted and
     * the earlier ones were silently dropped — `->environments('production')
     * ->between('01:00', '04:00')` ran in every environment.
     *
     * @param Closure|bool $callback
     * @return $this
     */
    public function when(Closure|bool $callback): static
    {
        $this->whenCallbacks[] = is_bool($callback) ? fn() => $callback : $callback;
        return $this;
    }

    /**
     * Skip when the callback returns true.
     *
     * Conditions accumulate: any one of them skips the task. See when() for
     * why these are no longer overwritten.
     *
     * @param Closure|bool $callback
     * @return $this
     */
    public function skip(Closure|bool $callback): static
    {
        $this->skipCallbacks[] = is_bool($callback) ? fn() => $callback : $callback;
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
     * Evaluated in the schedule's timezone when one is set via timezone(),
     * otherwise in the server's local time.
     *
     * @param string $startTime e.g. '09:00'
     * @param string $endTime e.g. '17:00'
     * @return $this
     */
    public function between(string $startTime, string $endTime): static
    {
        return $this->when(fn() => $this->isNowBetween($startTime, $endTime));
    }

    /**
     * Skip if the current time is between the given times (24h format HH:MM).
     *
     * Evaluated in the schedule's timezone when one is set via timezone(),
     * otherwise in the server's local time.
     *
     * @param string $startTime e.g. '23:00'
     * @param string $endTime e.g. '04:00'
     * @return $this
     */
    public function unlessBetween(string $startTime, string $endTime): static
    {
        return $this->skip(fn() => $this->isNowBetween($startTime, $endTime));
    }

    /**
     * Check whether the current time falls inside the given window.
     *
     * The timezone is read when this runs rather than when the window is
     * registered, so timezone() may be called before or after between().
     *
     * @param string $startTime Window start, format HH:MM.
     * @param string $endTime Window end, format HH:MM.
     * @return bool
     */
    protected function isNowBetween(string $startTime, string $endTime): bool
    {
        $now = (new DateTimeImmutable('now', $this->timezone))->format('H:i');

        if ($startTime <= $endTime) {
            return $now >= $startTime && $now <= $endTime;
        }

        // Overnight range (e.g. '22:00' to '06:00')
        return $now >= $startTime || $now <= $endTime;
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
        foreach ($this->whenCallbacks as $callback) {
            if (!$callback()) {
                return true;
            }
        }

        foreach ($this->skipCallbacks as $callback) {
            if ($callback()) {
                return true;
            }
        }

        return false;
    }

    // ─── Getters ─────────────────────────────────────────────────────────
    //
    // These exist so ScheduleRunCommand and ScheduleListCommand can read back
    // what the fluent methods above configured. They are public only because
    // those classes live in a different namespace, not because they are part
    // of the API this package offers — nothing in the documentation calls
    // them, and their shape follows the runner's needs rather than any
    // external contract. Marked @internal so that stays true: they may change
    // or disappear in a minor release.
    //
    // The fluent configuration methods above, isDue() and shouldSkip() are
    // deliberately not marked — those are the supported surface.

    /**
     * @internal
     */
    public function getCommandName(): string
    {
        return $this->commandName;
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @internal
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * @internal
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @internal
     */
    public function isWithoutOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    /**
     * @internal
     */
    public function isRunInBackground(): bool
    {
        return $this->runInBackground;
    }

    /**
     * @internal
     */
    public function getOutputPath(): ?string
    {
        return $this->outputPath;
    }

    /**
     * @internal
     */
    public function isAppendOutput(): bool
    {
        return $this->appendOutput;
    }

    /**
     * @internal
     */
    public function getBeforeCallback(): ?Closure
    {
        return $this->beforeCallback;
    }

    /**
     * @internal
     */
    public function getAfterCallback(): ?Closure
    {
        return $this->afterCallback;
    }

    /**
     * @internal
     */
    public function getOnFailureCallback(): ?Closure
    {
        return $this->onFailureCallback;
    }

    /**
     * @internal
     */
    public function getPingBeforeUrl(): ?string
    {
        return $this->pingBeforeUrl;
    }

    /**
     * @internal
     */
    public function getPingAfterUrl(): ?string
    {
        return $this->pingAfterUrl;
    }

    /**
     * @internal
     */
    public function getPingOnFailureUrl(): ?string
    {
        return $this->pingOnFailureUrl;
    }

    /**
     * @internal
     */
    public function isRunInMaintenanceMode(): bool
    {
        return $this->runInMaintenanceMode;
    }
}
