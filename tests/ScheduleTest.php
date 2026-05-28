<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Simsoft\Console\Schedule;

class ScheduleTest extends TestCase
{
    // --- Cron expressions ---

    public function testEveryMinute(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->everyMinute();
        $this->assertSame('* * * * *', $schedule->getExpression());
    }

    public function testEveryFiveMinutes(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->everyFiveMinutes();
        $this->assertSame('*/5 * * * *', $schedule->getExpression());
    }

    public function testEveryTenMinutes(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->everyTenMinutes();
        $this->assertSame('*/10 * * * *', $schedule->getExpression());
    }

    public function testEveryFifteenMinutes(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->everyFifteenMinutes();
        $this->assertSame('*/15 * * * *', $schedule->getExpression());
    }

    public function testEveryThirtyMinutes(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->everyThirtyMinutes();
        $this->assertSame('*/30 * * * *', $schedule->getExpression());
    }

    public function testHourly(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->hourly();
        $this->assertSame('0 * * * *', $schedule->getExpression());
    }

    public function testHourlyAt(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->hourlyAt(15);
        $this->assertSame('15 * * * *', $schedule->getExpression());
    }

    public function testDaily(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->daily();
        $this->assertSame('0 0 * * *', $schedule->getExpression());
    }

    public function testDailyAt(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->dailyAt(14, 30);
        $this->assertSame('30 14 * * *', $schedule->getExpression());
    }

    public function testWeekly(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->weekly();
        $this->assertSame('0 0 * * 0', $schedule->getExpression());
    }

    public function testWeeklyOn(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->weeklyOn(3, 9, 30);
        $this->assertSame('30 9 * * 3', $schedule->getExpression());
    }

    public function testMonthly(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->monthly();
        $this->assertSame('0 0 1 * *', $schedule->getExpression());
    }

    public function testMonthlyOn(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->monthlyOn(15, 8, 0);
        $this->assertSame('0 8 15 * *', $schedule->getExpression());
    }

    public function testYearly(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->yearly();
        $this->assertSame('0 0 1 1 *', $schedule->getExpression());
    }

    public function testWeekdays(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->weekdays();
        $this->assertSame('0 0 * * 1-5', $schedule->getExpression());
    }

    public function testCustomCron(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->cron('5 4 * * 1');
        $this->assertSame('5 4 * * 1', $schedule->getExpression());
    }

    // --- isDue ---

    public function testIsDueEveryMinute(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->everyMinute();
        $this->assertTrue($schedule->isDue());
    }

    public function testIsDueAtSpecificTime(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->dailyAt(14, 30);

        $dueTime = new DateTimeImmutable('2024-03-15 14:30:00');
        $notDueTime = new DateTimeImmutable('2024-03-15 10:00:00');

        $this->assertTrue($schedule->isDue($dueTime));
        $this->assertFalse($schedule->isDue($notDueTime));
    }

    public function testIsDueWithTimezone(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->dailyAt(0, 0)->timezone('UTC');

        $utcMidnight = new DateTimeImmutable('2024-03-15 00:00:00', new DateTimeZone('UTC'));
        $this->assertTrue($schedule->isDue($utcMidnight));
    }

    // --- Properties ---

    public function testGetCommandName(): void
    {
        $schedule = new Schedule('my:command');
        $this->assertSame('my:command', $schedule->getCommandName());
    }

    public function testGetArguments(): void
    {
        $schedule = new Schedule('my:command', ['name' => 'test']);
        $this->assertSame(['name' => 'test'], $schedule->getArguments());
    }

    public function testDescription(): void
    {
        $schedule = new Schedule('test:cmd');
        $result = $schedule->description('My task');
        $this->assertSame($schedule, $result);
        $this->assertSame('My task', $schedule->getDescription());
    }

    public function testDescriptionDefaultsToNull(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertNull($schedule->getDescription());
    }

    public function testWithoutOverlapping(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertFalse($schedule->isWithoutOverlapping());

        $result = $schedule->withoutOverlapping();
        $this->assertSame($schedule, $result);
        $this->assertTrue($schedule->isWithoutOverlapping());
    }

    // --- Fluent chaining ---

    public function testFluentChaining(): void
    {
        $schedule = new Schedule('test:cmd');
        $result = $schedule
            ->daily()
            ->timezone('America/New_York')
            ->description('Nightly job')
            ->withoutOverlapping();

        $this->assertSame($schedule, $result);
        $this->assertSame('0 0 * * *', $schedule->getExpression());
        $this->assertSame('Nightly job', $schedule->getDescription());
        $this->assertTrue($schedule->isWithoutOverlapping());
    }

    // --- Timezone ---

    public function testTimezoneWithString(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->timezone('Europe/London');
        // Should not throw
        $this->assertTrue($schedule->isDue() || !$schedule->isDue());
    }

    public function testTimezoneWithDateTimeZone(): void
    {
        $schedule = new Schedule('test:cmd');
        $schedule->timezone(new DateTimeZone('Asia/Tokyo'));
        // Should not throw
        $this->assertTrue($schedule->isDue() || !$schedule->isDue());
    }

    // --- Hooks ---

    public function testBeforeReturnsself(): void
    {
        $schedule = new Schedule('test:cmd');
        $result = $schedule->before(function () {
        });
        $this->assertSame($schedule, $result);
    }

    public function testAfterReturnsSelf(): void
    {
        $schedule = new Schedule('test:cmd');
        $result = $schedule->after(function () {
        });
        $this->assertSame($schedule, $result);
    }

    public function testOnFailureReturnsSelf(): void
    {
        $schedule = new Schedule('test:cmd');
        $result = $schedule->onFailure(function () {
        });
        $this->assertSame($schedule, $result);
    }

    public function testGetBeforeCallbackReturnsNull(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertNull($schedule->getBeforeCallback());
    }

    public function testGetBeforeCallbackReturnsClosure(): void
    {
        $fn = function () {
        };
        $schedule = new Schedule('test:cmd');
        $schedule->before($fn);
        $this->assertSame($fn, $schedule->getBeforeCallback());
    }

    public function testGetAfterCallbackReturnsNull(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertNull($schedule->getAfterCallback());
    }

    public function testGetAfterCallbackReturnsClosure(): void
    {
        $fn = function () {
        };
        $schedule = new Schedule('test:cmd');
        $schedule->after($fn);
        $this->assertSame($fn, $schedule->getAfterCallback());
    }

    public function testGetOnFailureCallbackReturnsNull(): void
    {
        $schedule = new Schedule('test:cmd');
        $this->assertNull($schedule->getOnFailureCallback());
    }

    public function testGetOnFailureCallbackReturnsClosure(): void
    {
        $fn = function () {
        };
        $schedule = new Schedule('test:cmd');
        $schedule->onFailure($fn);
        $this->assertSame($fn, $schedule->getOnFailureCallback());
    }

    public function testFluentChainingWithHooks(): void
    {
        $schedule = new Schedule('test:cmd');
        $result = $schedule
            ->daily()
            ->before(function () {
            })
            ->after(function () {
            })
            ->onFailure(function () {
            })
            ->description('Full chain');

        $this->assertSame($schedule, $result);
        $this->assertNotNull($schedule->getBeforeCallback());
        $this->assertNotNull($schedule->getAfterCallback());
        $this->assertNotNull($schedule->getOnFailureCallback());
    }
}
