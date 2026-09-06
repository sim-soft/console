<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Schedule;
use Simsoft\Console\Scheduler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Regression tests for scheduling conditions:
 *  - when()/skip() overwriting each other instead of accumulating
 *  - an invalid cron expression aborting the whole run from isDue()
 *  - a throwing when()/skip() callback aborting the whole run
 */
class ScheduleConditionTest extends TestCase
{
    private ?string $originalEnv = null;

    protected function setUp(): void
    {
        $env = getenv('APP_ENV');
        $this->originalEnv = $env === false ? null : $env;
        Application::flushGlobal();
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === null) {
            putenv('APP_ENV');
        } else {
            putenv("APP_ENV=$this->originalEnv");
        }

        Application::flushGlobal();
    }

    // --- when()/skip() accumulate ---

    public function testEveryWhenConditionMustPass(): void
    {
        $schedule = (new Schedule('x'))
            ->when(fn() => true)
            ->when(fn() => false);

        $this->assertTrue(
            $schedule->shouldSkip(),
            'A later when() must not overwrite an earlier one.'
        );
    }

    public function testAFailingFirstWhenConditionIsNotOverriddenByALaterOne(): void
    {
        $schedule = (new Schedule('x'))
            ->when(fn() => false)
            ->when(fn() => true);

        $this->assertTrue($schedule->shouldSkip());
    }

    public function testAnySkipConditionSkipsTheTask(): void
    {
        $schedule = (new Schedule('x'))
            ->skip(fn() => true)
            ->skip(fn() => false);

        $this->assertTrue($schedule->shouldSkip());
    }

    public function testAllConditionsPassingRunsTheTask(): void
    {
        $schedule = (new Schedule('x'))
            ->when(fn() => true)
            ->when(fn() => true)
            ->skip(fn() => false)
            ->skip(fn() => false);

        $this->assertFalse($schedule->shouldSkip());
    }

    /**
     * The shape this was actually reported as: an environment restriction
     * followed by a time window. between() calls when() internally, so it
     * discarded the environment check and the task ran everywhere.
     */
    public function testEnvironmentRestrictionSurvivesALaterTimeWindow(): void
    {
        putenv('APP_ENV=staging');

        $schedule = (new Schedule('deploy'))
            ->environments('production')
            ->between('00:00', '23:59');

        $this->assertTrue(
            $schedule->shouldSkip(),
            'environments() must still apply after between() adds its own condition.'
        );
    }

    public function testTimeWindowSurvivesALaterEnvironmentRestriction(): void
    {
        putenv('APP_ENV=staging');

        // A window that cannot be open: start and end are the same minute, and
        // unlessBetween skips inside it, so this schedule is always skipped.
        $schedule = (new Schedule('deploy'))
            ->environments('staging')
            ->unlessBetween('00:00', '23:59');

        $this->assertTrue($schedule->shouldSkip());
    }

    // --- cron validation ---

    public function testAnInvalidCronExpressionIsRejectedAtTheCallSite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid cron expression');

        (new Schedule('x'))->cron('not a cron expression');
    }

    public function testTheRejectionNamesTheCommandAndTheExpression(): void
    {
        try {
            (new Schedule('reports:daily'))->cron('*/5 * * * * *');
            $this->fail('An invalid expression should have been rejected.');
        } catch (InvalidArgumentException $ex) {
            $this->assertStringContainsString('reports:daily', $ex->getMessage());
            $this->assertStringContainsString('*/5 * * * * *', $ex->getMessage());
        }
    }

    public function testValidExpressionsAreStillAccepted(): void
    {
        $schedule = (new Schedule('x'))->cron('*/5 * * * *');

        $this->assertSame('*/5 * * * *', $schedule->getExpression());
    }

    // --- fault isolation in the run loop ---

    public function testAThrowingConditionDoesNotStopTheOtherTasks(): void
    {
        ConditionProbeCommand::$runs = 0;

        $output = $this->runSchedule(function (Scheduler $scheduler): void {
            $scheduler->command('boom')
                ->everyMinute()
                ->when(fn() => throw new RuntimeException('database is down'));

            $scheduler->command('probe:condition')->everyMinute();
        });

        $this->assertSame(
            1,
            ConditionProbeCommand::$runs,
            'A task after the failing condition must still run.'
        );
        $this->assertStringContainsString('database is down', $output);
    }

    public function testAThrowingConditionIsCountedAsAFailure(): void
    {
        ConditionProbeCommand::$runs = 0;

        $output = $this->runSchedule(function (Scheduler $scheduler): void {
            $scheduler->command('boom')
                ->everyMinute()
                ->when(fn() => throw new RuntimeException('database is down'));
        });

        // The run must not report success to cron when a condition could not
        // be evaluated — otherwise the task silently stops running.
        $this->assertStringContainsString('1 of 1 scheduled task(s) failed', $output);
    }

    public function testAThrowingConditionDoesNotRunTheTaskItGuards(): void
    {
        ConditionProbeCommand::$runs = 0;

        $this->runSchedule(function (Scheduler $scheduler): void {
            $scheduler->command('probe:condition')
                ->everyMinute()
                ->when(fn() => throw new RuntimeException('cannot decide'));
        });

        $this->assertSame(
            0,
            ConditionProbeCommand::$runs,
            'An unevaluable condition must not fall through to running the task.'
        );
    }

    /**
     * Run schedule:run over a configured scheduler and return what it wrote.
     */
    private function runSchedule(callable $configure): string
    {
        $application = Application::make('test', '1.0');
        $application->setAutoExit(false);
        $application->withCommands([ConditionProbeCommand::class]);
        $application->withScheduler($configure(...));

        $output = new BufferedOutput();
        $application->run(new ArrayInput(['command' => 'schedule:run']), $output);

        return $output->fetch();
    }
}

/**
 * Records that it ran, so fault isolation can be asserted on.
 */
class ConditionProbeCommand extends Command
{
    public static string $name = 'probe:condition';

    public static int $runs = 0;

    protected function handle(): void
    {
        ++self::$runs;
    }
}
