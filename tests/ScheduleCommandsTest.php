<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Scheduler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\ExceptionCommand;
use Tests\Fixtures\SimpleCommand;
use Throwable;

/**
 * Tests for ScheduleRunCommand and ScheduleListCommand.
 *
 * These two classes had no coverage at all, which is how the failure-reporting
 * defect below survived: a task throwing inside handle() is caught by
 * Command::execute() and converted to a FAILURE return code, so nothing ever
 * propagated to the scheduler's catch block. onFailure() never ran and
 * schedule:run reported success to cron.
 */
class ScheduleCommandsTest extends TestCase
{
    /**
     * Build an application with a scheduler configured by $configure.
     *
     * @param callable(Scheduler): void $configure
     */
    private function makeApp(callable $configure): Application
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new SimpleCommand());
        $app->addCommand(new ExceptionCommand());
        $app->withScheduler($configure);

        return $app;
    }

    /**
     * @param callable(Scheduler): void $configure
     * @return array{status: int, output: string}
     */
    private function runSchedule(callable $configure, string $command = 'schedule:run'): array
    {
        $output = new BufferedOutput();
        $status = $this->makeApp($configure)->doRun(
            new ArrayInput(['command' => $command]),
            $output
        );

        return ['status' => $status, 'output' => $output->fetch()];
    }

    // --- schedule:run: task dispatch ---

    public function testRunReportsWhenNothingIsDue(): void
    {
        // Due only at 03:00 on the 1st of January — not now, whenever "now" is.
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:simple')->cron('0 3 1 1 *')
        );

        $this->assertSame(Command::SUCCESS, $result['status']);
        $this->assertStringContainsString('No scheduled commands are ready to run', $result['output']);
    }

    public function testRunExecutesADueTask(): void
    {
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:simple')->everyMinute()
        );

        $this->assertSame(Command::SUCCESS, $result['status']);
        $this->assertStringContainsString('Running: test:simple', $result['output']);
        $this->assertStringContainsString('Simple command executed', $result['output']);
    }

    public function testRunUsesTheDescriptionWhenSet(): void
    {
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:simple')->everyMinute()->description('Nightly sync')
        );

        $this->assertStringContainsString('Running: Nightly sync', $result['output']);
    }

    public function testRunSkipsATaskWhoseConditionIsFalse(): void
    {
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:simple')->everyMinute()->when(false)
        );

        $this->assertSame(Command::SUCCESS, $result['status']);
        $this->assertStringContainsString('Skipped (condition)', $result['output']);
        $this->assertStringNotContainsString('Simple command executed', $result['output']);
    }

    // --- schedule:run: failure reporting ---

    public function testFailingTaskInvokesTheOnFailureHook(): void
    {
        $captured = null;

        $this->runSchedule(function (Scheduler $s) use (&$captured) {
            $s->command('test:exception')->everyMinute()
                ->onFailure(function (Throwable $ex) use (&$captured) {
                    $captured = $ex;
                });
        });

        $this->assertInstanceOf(Throwable::class, $captured);
    }

    public function testFailingTaskDoesNotInvokeTheAfterHook(): void
    {
        // after() is documented as "callback after success". It previously fired
        // for failed tasks too, with exitCode=1.
        $afterFired = false;

        $this->runSchedule(function (Scheduler $s) use (&$afterFired) {
            $s->command('test:exception')->everyMinute()
                ->after(function () use (&$afterFired) {
                    $afterFired = true;
                });
        });

        $this->assertFalse($afterFired);
    }

    public function testFailingTaskReportsFailureExitCode(): void
    {
        // The whole point: cron must not see success when a task failed.
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:exception')->everyMinute()
        );

        $this->assertSame(Command::FAILURE, $result['status']);
    }

    public function testFailingTaskReportsTheFailureInOutput(): void
    {
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:exception')->everyMinute()
        );

        $this->assertStringContainsString('Failed [test:exception]', $result['output']);
        $this->assertStringContainsString('1 of 1 scheduled task(s) failed', $result['output']);
    }

    public function testSucceedingTaskInvokesTheAfterHookWithItsExitCode(): void
    {
        $exitCode = null;

        $this->runSchedule(function (Scheduler $s) use (&$exitCode) {
            $s->command('test:simple')->everyMinute()
                ->after(function (int $code) use (&$exitCode) {
                    $exitCode = $code;
                });
        });

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testSucceedingTaskInvokesTheBeforeHook(): void
    {
        $beforeFired = false;

        $this->runSchedule(function (Scheduler $s) use (&$beforeFired) {
            $s->command('test:simple')->everyMinute()
                ->before(function () use (&$beforeFired) {
                    $beforeFired = true;
                });
        });

        $this->assertTrue($beforeFired);
    }

    public function testSucceedingTaskDoesNotInvokeTheOnFailureHook(): void
    {
        $failureFired = false;

        $this->runSchedule(function (Scheduler $s) use (&$failureFired) {
            $s->command('test:simple')->everyMinute()
                ->onFailure(function () use (&$failureFired) {
                    $failureFired = true;
                });
        });

        $this->assertFalse($failureFired);
    }

    // --- schedule:run: fault isolation ---

    public function testAFailingTaskDoesNotPreventLaterTasksFromRunning(): void
    {
        $result = $this->runSchedule(function (Scheduler $s) {
            $s->command('test:exception')->everyMinute();
            $s->command('test:simple')->everyMinute();
        });

        $this->assertStringContainsString('Failed [test:exception]', $result['output']);
        $this->assertStringContainsString('Simple command executed', $result['output']);
        $this->assertSame(Command::FAILURE, $result['status']);
    }

    public function testFailureCountReflectsOnlyTheFailedTasks(): void
    {
        $result = $this->runSchedule(function (Scheduler $s) {
            $s->command('test:exception')->everyMinute();
            $s->command('test:simple')->everyMinute();
            $s->command('test:exception')->everyMinute();
        });

        $this->assertStringContainsString('2 of 3 scheduled task(s) failed', $result['output']);
    }

    // --- schedule:run: output capture ---

    public function testSendOutputToWritesTaskOutputToFile(): void
    {
        $path = sys_get_temp_dir() . '/schedule-output-' . uniqid() . '.log';

        try {
            $this->runSchedule(
                fn(Scheduler $s) => $s->command('test:simple')->everyMinute()->sendOutputTo($path)
            );

            $this->assertFileExists($path);
            $this->assertStringContainsString('Simple command executed', (string)file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function testAppendOutputToPreservesExistingContent(): void
    {
        $path = sys_get_temp_dir() . '/schedule-append-' . uniqid() . '.log';
        file_put_contents($path, "PREEXISTING\n");

        try {
            $this->runSchedule(
                fn(Scheduler $s) => $s->command('test:simple')->everyMinute()->appendOutputTo($path)
            );

            $contents = (string)file_get_contents($path);
            $this->assertStringContainsString('PREEXISTING', $contents);
            $this->assertStringContainsString('Simple command executed', $contents);
        } finally {
            @unlink($path);
        }
    }

    // --- schedule:list ---

    public function testListReportsWhenNothingIsRegistered(): void
    {
        $result = $this->runSchedule(fn(Scheduler $s) => null, 'schedule:list');

        $this->assertSame(Command::SUCCESS, $result['status']);
        $this->assertStringContainsString('No scheduled tasks registered', $result['output']);
    }

    public function testListShowsExpressionAndCommandName(): void
    {
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:simple')->everyFiveMinutes(),
            'schedule:list'
        );

        $this->assertSame(Command::SUCCESS, $result['status']);
        $this->assertStringContainsString('*/5 * * * *', $result['output']);
        $this->assertStringContainsString('test:simple', $result['output']);
    }

    public function testListShowsDescriptionWhenSetAndDashOtherwise(): void
    {
        $result = $this->runSchedule(function (Scheduler $s) {
            $s->command('test:simple')->everyMinute()->description('Has a description');
            $s->command('test:exception')->everyMinute();
        }, 'schedule:list');

        $this->assertStringContainsString('Has a description', $result['output']);
        $this->assertMatchesRegularExpression('/test:exception\s*\|\s*-/', $result['output']);
    }

    public function testListDoesNotExecuteAnyTask(): void
    {
        $result = $this->runSchedule(
            fn(Scheduler $s) => $s->command('test:simple')->everyMinute(),
            'schedule:list'
        );

        $this->assertStringNotContainsString('Simple command executed', $result['output']);
    }
}
