<?php

namespace Simsoft\Console\Commands;

use RuntimeException;
use Simsoft\Console\Command;
use Simsoft\Console\Schedule;
use Simsoft\Console\Scheduler;
use Symfony\Component\Console\Input\ArrayInput;
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
                $this->runInBackground($schedule);
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
            $input = new ArrayInput(
                array_merge(['command' => $schedule->getCommandName()], $schedule->getArguments())
            );

            $exitCode = $this->getApplication()->doRun($input, $bufferedOutput);

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

        return $this->call($schedule->getCommandName(), $schedule->getArguments());
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
            pclose(popen("start /B $command", 'r'));
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
        $script = escapeshellarg($_SERVER['argv'][0] ?? 'console');
        $args = escapeshellarg($schedule->getCommandName());

        foreach ($schedule->getArguments() as $key => $value) {
            if (str_starts_with($key, '--')) {
                $args .= ' ' . escapeshellarg("$key=$value");
                continue;
            }
            $args .= ' ' . escapeshellarg((string)$value);
        }

        $output = '';
        if ($schedule->getOutputPath()) {
            $redirect = $schedule->isAppendOutput() ? ">>" : ">";
            $output = "$redirect " . escapeshellarg($schedule->getOutputPath());
        }

        return trim("$php $script $args $output");
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
