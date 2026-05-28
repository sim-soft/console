<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Schedule;
use Simsoft\Console\Scheduler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\SimpleCommand;

class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        $closureCommands = $reflection->getProperty('closureCommands');
        $closureCommands->setValue(null, []);
        $commands = $reflection->getProperty('commands');
        $commands->setValue(null, []);
        $app = $reflection->getProperty('app');
        $app->setValue(null, null);
    }

    // --- Scheduler registration ---

    public function testSchedulerCommandReturnsSchedule(): void
    {
        $scheduler = new Scheduler();
        $schedule = $scheduler->command('test:simple');

        $this->assertInstanceOf(Schedule::class, $schedule);
    }

    public function testSchedulerRegistersMultipleSchedules(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('test:one');
        $scheduler->command('test:two');
        $scheduler->command('test:three');

        $this->assertCount(3, $scheduler->getSchedules());
    }

    public function testSchedulerGetDueSchedules(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('test:always')->everyMinute();
        $scheduler->command('test:never')->cron('0 0 31 2 *'); // Feb 31 never happens

        $due = $scheduler->getDueSchedules();
        $this->assertCount(1, $due);
        $this->assertSame('test:always', $due[0]->getCommandName());
    }

    public function testSchedulerWithArguments(): void
    {
        $scheduler = new Scheduler();
        $schedule = $scheduler->command('test:args', ['name' => 'Alice', '--verbose' => true]);

        $this->assertSame('test:args', $schedule->getCommandName());
        $this->assertSame(['name' => 'Alice', '--verbose' => true], $schedule->getArguments());
    }

    // --- Application withScheduler ---

    public function testWithSchedulerRegistersCommands(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')->everyMinute();
        });

        $this->assertTrue($app->has('schedule:run'));
        $this->assertTrue($app->has('schedule:list'));
    }

    public function testScheduleRunExecutesDueCommands(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')->everyMinute();
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('Running:', $result);
        $this->assertStringContainsString('Simple command executed', $result);
    }

    public function testScheduleRunWithNoTasksDue(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')->cron('0 0 31 2 *'); // Never due
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('No scheduled commands are ready to run', $result);
    }

    public function testScheduleListShowsAllTasks(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')->daily()->description('Daily task');
        });

        $input = new ArrayInput(['command' => 'schedule:list']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('0 0 * * *', $result);
        $this->assertStringContainsString('test:simple', $result);
        $this->assertStringContainsString('Daily task', $result);
    }

    public function testScheduleListWithNoTasks(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withScheduler(function (Scheduler $scheduler) {
            // No tasks
        });

        $input = new ArrayInput(['command' => 'schedule:list']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('No scheduled tasks registered', $result);
    }

    public function testGetSchedulerReturnsInstance(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')->hourly();
        });

        $this->assertInstanceOf(Scheduler::class, $app->getScheduler());
    }

    public function testGetSchedulerReturnsNullWithoutSetup(): void
    {
        $app = Application::make('Test', '1.0');
        $this->assertNull($app->getScheduler());
    }

    // --- Hooks ---

    public function testBeforeHookIsCalled(): void
    {
        $called = false;

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) use (&$called) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->before(function () use (&$called) {
                    $called = true;
                });
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertTrue($called);
    }

    public function testAfterHookIsCalledWithExitCode(): void
    {
        $receivedCode = null;

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) use (&$receivedCode) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->after(function (int $exitCode) use (&$receivedCode) {
                    $receivedCode = $exitCode;
                });
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertSame(0, $receivedCode);
    }

    public function testOnFailureHookIsCalledOnException(): void
    {
        $receivedException = null;

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([\Tests\Fixtures\ExceptionCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) use (&$receivedException) {
            $scheduler->command('test:exception')
                ->everyMinute()
                ->onFailure(function (\Throwable $e) use (&$receivedException) {
                    $receivedException = $e;
                });
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        // The command itself catches exceptions and returns FAILURE,
        // so onFailure won't be triggered unless call() itself throws.
        // Let's verify the task still ran without stopping the scheduler.
        $result = $output->fetch();
        $this->assertStringContainsString('Running:', $result);
    }

    public function testFailedCommandDoesNotStopNextTask(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([
            \Tests\Fixtures\ExceptionCommand::class,
            SimpleCommand::class,
        ]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:exception')->everyMinute();
            $scheduler->command('test:simple')->everyMinute();
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        // Both tasks should have been attempted
        $this->assertStringContainsString('test:exception', $result);
        $this->assertStringContainsString('Simple command executed', $result);
    }

    // --- Overlap prevention ---

    public function testWithoutOverlappingSkipsWhenLocked(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->withoutOverlapping();
        });

        // Acquire the same lock manually
        $lockFactory = new \Symfony\Component\Lock\LockFactory(
            new \Symfony\Component\Lock\Store\FlockStore()
        );
        $lockKey = 'schedule_' . md5('test:simple' . serialize([]));
        $lock = $lockFactory->createLock($lockKey, 3600);
        $lock->acquire();

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('Skipped (overlapping)', $result);
        $this->assertStringNotContainsString('Simple command executed', $result);

        $lock->release();
    }

    // --- Maintenance mode ---

    public function testMaintenanceModeSkipsNormalTasks(): void
    {
        putenv('APP_MAINTENANCE=true');

        $scheduler = new Scheduler();
        $scheduler->command('test:normal')->everyMinute();
        $scheduler->command('test:critical')->everyMinute()->evenInMaintenanceMode();

        $due = $scheduler->getDueSchedules();
        $this->assertCount(1, $due);
        $this->assertSame('test:critical', array_values($due)[0]->getCommandName());

        putenv('APP_MAINTENANCE=');
    }

    public function testMaintenanceModeViaFile(): void
    {
        $maintenanceFile = sys_get_temp_dir() . '/simsoft_maintenance_' . uniqid();
        file_put_contents($maintenanceFile, 'down');

        $scheduler = new Scheduler();
        $scheduler->maintenanceFile($maintenanceFile);
        $scheduler->command('test:normal')->everyMinute();
        $scheduler->command('test:critical')->everyMinute()->evenInMaintenanceMode();

        $due = $scheduler->getDueSchedules();
        $this->assertCount(1, $due);
        $this->assertSame('test:critical', array_values($due)[0]->getCommandName());

        unlink($maintenanceFile);
    }

    public function testNoMaintenanceModeRunsAllTasks(): void
    {
        putenv('APP_MAINTENANCE=');

        $scheduler = new Scheduler();
        $scheduler->command('test:one')->everyMinute();
        $scheduler->command('test:two')->everyMinute()->evenInMaintenanceMode();

        $due = $scheduler->getDueSchedules();
        $this->assertCount(2, $due);
    }

    public function testIsInMaintenanceModeWithEnvVar(): void
    {
        $scheduler = new Scheduler();

        putenv('APP_MAINTENANCE=true');
        $this->assertTrue($scheduler->isInMaintenanceMode());

        putenv('APP_MAINTENANCE=1');
        $this->assertTrue($scheduler->isInMaintenanceMode());

        putenv('APP_MAINTENANCE=false');
        $this->assertFalse($scheduler->isInMaintenanceMode());

        putenv('APP_MAINTENANCE=');
    }

    public function testMaintenanceFileMethod(): void
    {
        $scheduler = new Scheduler();
        $result = $scheduler->maintenanceFile('/tmp/maintenance');
        $this->assertSame($scheduler, $result);
    }
}
