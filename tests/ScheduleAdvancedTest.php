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

class ScheduleAdvancedTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        $closureCommands = $reflection->getProperty('closureCommands');
        $closureCommands->setValue(null, []);
        $commands = $reflection->getProperty('commands');
        $commands->setValue(null, []);
        $app = $reflection->getProperty('app');
        $app->setValue(null, null);

        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'simsoft_sched_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    // --- Conditional: when() ---

    public function testWhenTrueAllowsExecution(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->when(fn() => true);
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('Simple command executed', $output->fetch());
    }

    public function testWhenFalseSkipsExecution(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->when(fn() => false);
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('Skipped (condition)', $result);
        $this->assertStringNotContainsString('Simple command executed', $result);
    }

    public function testWhenWithBooleanTrue(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->when(true);
        $this->assertFalse($schedule->shouldSkip());
    }

    public function testWhenWithBooleanFalse(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->when(false);
        $this->assertTrue($schedule->shouldSkip());
    }

    // --- Conditional: skip() ---

    public function testSkipTrueSkipsExecution(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->skip(fn() => true);
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('Skipped (condition)', $result);
    }

    public function testSkipFalseAllowsExecution(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->skip(fn() => false);
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('Simple command executed', $output->fetch());
    }

    public function testSkipWithBooleanTrue(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->skip(true);
        $this->assertTrue($schedule->shouldSkip());
    }

    // --- Conditional: environments() ---

    public function testEnvironmentsMatchesCurrentEnv(): void
    {
        putenv('APP_ENV=testing');
        $schedule = new Schedule('test:cmd');
        $schedule->environments(['testing', 'staging']);
        $this->assertFalse($schedule->shouldSkip());
        putenv('APP_ENV=');
    }

    public function testEnvironmentsSkipsNonMatchingEnv(): void
    {
        putenv('APP_ENV=production');
        $schedule = new Schedule('test:cmd');
        $schedule->environments('staging');
        $this->assertTrue($schedule->shouldSkip());
        putenv('APP_ENV=');
    }

    // --- Output capture ---

    public function testSendOutputToWritesFile(): void
    {
        $outputFile = $this->tempDir . '/output.log';

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) use ($outputFile) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->sendOutputTo($outputFile);
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertFileExists($outputFile);
        $content = file_get_contents($outputFile);
        $this->assertStringContainsString('test:simple', $content);
    }

    public function testAppendOutputToAppendsFile(): void
    {
        $outputFile = $this->tempDir . '/append.log';
        file_put_contents($outputFile, "EXISTING\n");

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);
        $app->withScheduler(function (Scheduler $scheduler) use ($outputFile) {
            $scheduler->command('test:simple')
                ->everyMinute()
                ->appendOutputTo($outputFile);
        });

        $input = new ArrayInput(['command' => 'schedule:run']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $content = file_get_contents($outputFile);
        $this->assertStringContainsString('EXISTING', $content);
        $this->assertStringContainsString('test:simple', $content);
    }

    // --- Schedule properties ---

    public function testSendOutputToSetsPath(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->sendOutputTo('/tmp/out.log');
        $this->assertSame('/tmp/out.log', $schedule->getOutputPath());
        $this->assertFalse($schedule->isAppendOutput());
    }

    public function testAppendOutputToSetsPathAndFlag(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->appendOutputTo('/tmp/out.log');
        $this->assertSame('/tmp/out.log', $schedule->getOutputPath());
        $this->assertTrue($schedule->isAppendOutput());
    }

    public function testRunInBackground(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertFalse($schedule->isRunInBackground());
        $schedule->runInBackground();
        $this->assertTrue($schedule->isRunInBackground());
    }

    // --- Ping URLs ---

    public function testPingBeforeUrl(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->pingBefore('https://example.com/start');
        $this->assertSame('https://example.com/start', $schedule->getPingBeforeUrl());
    }

    public function testThenPingUrl(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->thenPing('https://example.com/done');
        $this->assertSame('https://example.com/done', $schedule->getPingAfterUrl());
    }

    public function testPingOnFailureUrl(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->pingOnFailure('https://example.com/fail');
        $this->assertSame('https://example.com/fail', $schedule->getPingOnFailureUrl());
    }

    public function testPingUrlsDefaultToNull(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertNull($schedule->getPingBeforeUrl());
        $this->assertNull($schedule->getPingAfterUrl());
        $this->assertNull($schedule->getPingOnFailureUrl());
    }

    // --- Additional frequency methods ---

    public function testTwiceDaily(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->twiceDaily(1, 13);
        $this->assertSame('0 1,13 * * *', $schedule->getExpression());
    }

    public function testQuarterly(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->quarterly();
        $this->assertSame('0 0 1 1-12/3 *', $schedule->getExpression());
    }

    public function testWeekends(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->weekends();
        $this->assertSame('0 0 * * 0,6', $schedule->getExpression());
    }

    // --- Full fluent chain ---

    public function testFullFluentChain(): void
    {
        $schedule = new Schedule('test:cmd');
        $result = $schedule
            ->dailyAt(3, 0)
            ->timezone('UTC')
            ->description('Full chain')
            ->withoutOverlapping()
            ->runInBackground()
            ->when(fn() => true)
            ->skip(fn() => false)
            ->between('00:00', '23:59')
            ->evenInMaintenanceMode()
            ->sendOutputTo('/tmp/test.log')
            ->before(function () {
            })
            ->after(function () {
            })
            ->onFailure(function () {
            })
            ->pingBefore('https://example.com/start')
            ->thenPing('https://example.com/done')
            ->pingOnFailure('https://example.com/fail');

        $this->assertSame($schedule, $result);
        $this->assertSame('0 3 * * *', $schedule->getExpression());
        $this->assertTrue($schedule->isRunInBackground());
        $this->assertTrue($schedule->isWithoutOverlapping());
        $this->assertTrue($schedule->isRunInMaintenanceMode());
    }

    // --- between() / unlessBetween() ---

    public function testBetweenWithinRange(): void
    {
        $schedule = new Schedule('test:cmd');
        $currentHour = (int)date('H');
        $currentMinute = (int)date('i');

        // Set range that includes current time
        $start = sprintf('%02d:%02d', $currentHour, 0);
        $end = sprintf('%02d:%02d', $currentHour, 59);

        $schedule->between($start, $end);
        $this->assertFalse($schedule->shouldSkip());
    }

    public function testBetweenOutsideRange(): void
    {
        $schedule = new Schedule('test:cmd');
        // Use a time range that's definitely not now
        $futureHour = ((int)date('H') + 5) % 24;
        $start = sprintf('%02d:00', $futureHour);
        $end = sprintf('%02d:30', $futureHour);

        $schedule->between($start, $end);
        $this->assertTrue($schedule->shouldSkip());
    }

    public function testUnlessBetweenWithinRange(): void
    {
        $schedule = new Schedule('test:cmd');
        $currentHour = (int)date('H');

        // Set range that includes current time — should skip
        $start = sprintf('%02d:00', $currentHour);
        $end = sprintf('%02d:59', $currentHour);

        $schedule->unlessBetween($start, $end);
        $this->assertTrue($schedule->shouldSkip());
    }

    public function testUnlessBetweenOutsideRange(): void
    {
        $schedule = new Schedule('test:cmd');
        $futureHour = ((int)date('H') + 5) % 24;
        $start = sprintf('%02d:00', $futureHour);
        $end = sprintf('%02d:30', $futureHour);

        $schedule->unlessBetween($start, $end);
        $this->assertFalse($schedule->shouldSkip());
    }

    public function testBetweenOvernightRange(): void
    {
        // Test the logic directly: overnight range 22:00 to 06:00
        // means "run if time >= 22:00 OR time <= 06:00"
        $schedule = new Schedule('test:cmd');
        $schedule->between('22:00', '06:00');

        $currentHour = (int)date('H');
        $currentMinute = (int)date('i');
        $now = sprintf('%02d:%02d', $currentHour, $currentMinute);

        // The condition: now >= 22:00 OR now <= 06:00
        $expectedInRange = ($now >= '22:00' || $now <= '06:00');

        if ($expectedInRange) {
            $this->assertFalse($schedule->shouldSkip());
        } else {
            $this->assertTrue($schedule->shouldSkip());
        }
    }

    // --- evenInMaintenanceMode() ---

    public function testEvenInMaintenanceMode(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertFalse($schedule->isRunInMaintenanceMode());
        $schedule->evenInMaintenanceMode();
        $this->assertTrue($schedule->isRunInMaintenanceMode());
    }
}
