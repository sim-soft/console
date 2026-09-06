<?php

namespace Simsoft\Console\Commands;

use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Schedule;
use Simsoft\Console\Scheduler;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Throwable;

/**
 * Class ScheduleRunCommand
 *
 * Runs all due scheduled tasks.
 */
class ScheduleRunCommand extends Command
{
    public static string $name = 'schedule:run';
    public static string $description = 'Run all due scheduled tasks';

    protected bool $messageTimeStamp = true;

    private ?LockFactory $lockFactory = null;

    /** @var int Tasks that failed during this run. */
    private int $failures = 0;

    /**
     * Constructor.
     *
     * @param Scheduler $scheduler
     */
    public function __construct(protected Scheduler $scheduler)
    {
        parent::__construct();
    }

    /**
     * Get the lock factory (lazy-initialized).
     *
     * @return LockFactory
     */
    private function getLockFactory(): LockFactory
    {
        if ($this->lockFactory === null) {
            $this->lockFactory = new LockFactory(new FlockStore());
        }
        return $this->lockFactory;
    }

    /**
     * @inheritdoc
     */
    protected function handle(): void
    {
        $dueSchedules = $this->scheduler->getDueSchedules();

        if (empty($dueSchedules)) {
            $this->info('No scheduled commands are ready to run.');
            return;
        }

        $this->failures = 0;

        foreach ($dueSchedules as $schedule) {
            // Conditional scheduling
            if ($schedule->shouldSkip()) {
                $desc = $schedule->getDescription() ?? $schedule->getCommandName();
                $this->comment("Skipped (condition): $desc");
                continue;
            }

            if ($schedule->isRunInBackground()) {
                // A launch failure must not abort the remaining tasks, and must
                // still be reported like any other failure.
                try {
                    $this->runInBackground($schedule);
                } catch (Throwable $ex) {
                    ++$this->failures;
                    $this->error($ex->getMessage());
                }
                continue;
            }

            $this->runScheduledTask($schedule);
        }

        // Every task still ran — failures are isolated per task. But the run as a
        // whole must not report success to cron when something failed, or a broken
        // task stays invisible until someone reads the logs.
        if ($this->failures > 0) {
            throw new RuntimeException(sprintf(
                '%d of %d scheduled task(s) failed.',
                $this->failures,
                count($dueSchedules)
            ));
        }
    }

    /**
     * Run a single scheduled task with locking, hooks, output capture, and pings.
     *
     * @param Schedule $schedule
     * @return void
     */
    private function runScheduledTask(Schedule $schedule): void
    {
        $description = $schedule->getDescription() ?? $schedule->getCommandName();
        $lock = null;

        // Acquire lock if overlap prevention is enabled
        if ($schedule->isWithoutOverlapping()) {
            $lockKey = 'schedule_' . md5($schedule->getCommandName() . serialize($schedule->getArguments()));
            $lock = $this->getLockFactory()->createLock($lockKey, 3600);

            if (!$lock->acquire()) {
                $this->comment("Skipped (overlapping): $description");
                return;
            }
        }

        try {
            // Ping before
            $this->ping($schedule->getPingBeforeUrl());

            // Before hook
            $before = $schedule->getBeforeCallback();
            if ($before) {
                $before();
            }

            $this->info("Running: $description");

            // Execute with output capture
            $exitCode = $this->executeCommand($schedule);

            // A command that throws inside handle() is caught by Command::execute(),
            // which reports the message and returns FAILURE. Nothing propagates here,
            // so a non-zero exit code is the only signal a task failed. Without this
            // check the catch block below was dead for the ordinary case: onFailure
            // never ran, the failure URL was never pinged, and the run reported
            // success to cron.
            if ($exitCode !== Command::SUCCESS) {
                throw new RuntimeException("Command exited with code $exitCode.");
            }

            // After hook
            $after = $schedule->getAfterCallback();
            if ($after) {
                $after($exitCode);
            }

            // Ping after success
            $this->ping($schedule->getPingAfterUrl());

        } catch (Throwable $ex) {
            ++$this->failures;

            $this->error("Failed [$description]: {$ex->getMessage()}");

            // Failure hook
            $onFailure = $schedule->getOnFailureCallback();
            if ($onFailure) {
                $onFailure($ex);
            }

            // Ping on failure
            $this->ping($schedule->getPingOnFailureUrl());
        } finally {
            $lock?->release();
        }
    }

    /**
     * Execute the command, capturing output to file if configured.
     *
     * @param Schedule $schedule
     * @return int Exit code
     */
    private function executeCommand(Schedule $schedule): int
    {
        if ($schedule->getOutputPath()) {
            // Capture output to buffer, then write to file
            $bufferedOutput = new BufferedOutput();

            // A scheduled task runs unattended by definition, so it must not
            // prompt even when schedule:run was started from a terminal.
            $input = Application::programmaticInput(
                $schedule->getCommandName(),
                $schedule->getArguments()
            );

            $exitCode = $this->requireApplication(__FUNCTION__)->doRun($input, $bufferedOutput);

            $content = $bufferedOutput->fetch();

            // Also display in console
            if ($content) {
                $this->output->write($content);
            }

            // Write to file
            $flags = $schedule->isAppendOutput() ? FILE_APPEND : 0;
            $header = '[' . date('Y-m-d H:i:s') . '] ' . $schedule->getCommandName() . "\n";
            file_put_contents($schedule->getOutputPath(), $header . $content . "\n", $flags);

            return $exitCode;
        }

        // Not $this->call(), which inherits this command's interactivity: an
        // operator running schedule:run by hand would make every task able to
        // prompt, and the same task would then behave differently under cron.
        return $this->requireApplication(__FUNCTION__)->doRun(
            Application::programmaticInput($schedule->getCommandName(), $schedule->getArguments()),
            $this->output
        );
    }

    /**
     * Run a task in a background process.
     *
     * @param Schedule $schedule
     * @return void
     */
    private function runInBackground(Schedule $schedule): void
    {
        $description = $schedule->getDescription() ?? $schedule->getCommandName();
        $this->info("Starting background: $description");

        $command = $this->buildBackgroundCommand($schedule);

        if (PHP_OS_FAMILY === 'Windows') {
            $process = popen("start /B $command", 'r');

            // popen() returns false if the process could not be started. Passing
            // that straight to pclose() was a TypeError on top of an already
            // failed launch, which buried the real problem.
            if ($process === false) {
                throw new RuntimeException("Unable to start background process for: $description");
            }

            pclose($process);
            return;
        }

        exec("$command > /dev/null 2>&1 &");
    }

    /**
     * Build the shell command for background execution.
     *
     * @param Schedule $schedule
     * @return string
     */
    private function buildBackgroundCommand(Schedule $schedule): string
    {
        // Paths may contain spaces (e.g. C:\Program Files\php\php.exe).
        $php = escapeshellarg(PHP_BINARY);
        $script = escapeshellarg($this->scriptPath());
        $args = escapeshellarg($schedule->getCommandName());

        foreach ($schedule->getArguments() as $key => $value) {
            // A multi-value option is an array in ArrayInput, and is legal here.
            // Interpolating it produced the literal string "Array" behind an
            // "Array to string conversion" warning, so the background task ran
            // with an argument nobody wrote. Repeat the option instead, which is
            // how the terminal would have passed it.
            foreach (is_array($value) ? $value : [$value] as $item) {
                $args .= ' ' . escapeshellarg(
                    str_starts_with((string)$key, '--')
                        ? $key . '=' . $this->stringifyArgument($item, (string)$key)
                        : $this->stringifyArgument($item, (string)$key)
                );
            }
        }

        $output = '';
        if ($schedule->getOutputPath()) {
            $redirect = $schedule->isAppendOutput() ? ">>" : ">";
            $output = "$redirect " . escapeshellarg($schedule->getOutputPath());
        }

        return trim("$php $script $args $output");
    }

    /**
     * Get the entry script to re-invoke for a background task.
     *
     * @return string
     */
    private function scriptPath(): string
    {
        $argv = $_SERVER['argv'] ?? null;

        // $_SERVER['argv'] is absent under some SAPIs and, when register_argc_argv
        // is off, can be present as something other than a list. The old
        // `$_SERVER['argv'][0] ?? 'console'` only covered the absent case: a
        // string there indexed to its first character, so the background task was
        // launched against a one-letter path that does not exist.
        if (is_array($argv) && isset($argv[0]) && is_string($argv[0])) {
            return $argv[0];
        }

        return 'console';
    }

    /**
     * Render a scheduled argument as a shell argument.
     *
     * @param mixed $value
     * @param string $key Named in the error.
     * @return string
     * @throws RuntimeException If the value has no faithful string form.
     */
    private function stringifyArgument(mixed $value, string $key): string
    {
        if (is_scalar($value)) {
            // Booleans stringify to "1"/"" otherwise, and an empty argument is
            // indistinguishable from an omitted one on the command line.
            return is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        }

        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        throw new RuntimeException(sprintf(
            'Scheduled argument "%s" is %s, which cannot be passed to a background process.',
            $key,
            get_debug_type($value)
        ));
    }

    /**
     * Send a GET request to a URL (fire-and-forget).
     *
     * @param string|null $url
     * @return void
     */
    private function ping(?string $url): void
    {
        if ($url === null) {
            return;
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                ],
            ]);
            @file_get_contents($url, false, $context);
        } catch (Throwable) {
            // Fire-and-forget — don't let ping failures break the scheduler
        }
    }
}
