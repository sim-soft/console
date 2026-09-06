<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simsoft\Console\Schedule;

/**
 * Regression tests for scheduling defects:
 *  - between()/unlessBetween() ignoring the schedule timezone
 *  - frequency helpers accepting out-of-range values and failing later
 */
class ScheduleHardeningTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    /**
     * Pick a timezone whose current hour differs from the server's, so a
     * timezone-blind implementation cannot pass by coincidence.
     */
    private function timezoneOffsetFromServerBy(int $hours): DateTimeZone
    {
        $target = (new DateTimeImmutable('now'))->modify("$hours hours");

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $zone = new DateTimeZone($identifier);
            $now = new DateTimeImmutable('now', $zone);

            if ($now->format('H') === $target->format('H')) {
                return $zone;
            }
        }

        $this->markTestSkipped("No timezone found offset by $hours hours from the server.");
    }

    // --- between() must honour the schedule timezone ---

    public function testBetweenUsesTheScheduleTimezone(): void
    {
        // A window around "now" in a zone six hours away from the server.
        $zone = $this->timezoneOffsetFromServerBy(6);
        $remoteNow = new DateTimeImmutable('now', $zone);

        $start = $remoteNow->modify('-1 hour')->format('H:i');
        $end = $remoteNow->modify('+1 hour')->format('H:i');

        // Skip if the window straddles midnight; the overnight branch is
        // covered separately and would make this assertion ambiguous.
        if ($start > $end) {
            $this->markTestSkipped('Window wraps midnight for the chosen timezone.');
        }

        $schedule = (new Schedule('x'))->timezone($zone)->between($start, $end);

        // The window brackets "now" in the remote zone but not on the server.
        $this->assertFalse($schedule->shouldSkip(), 'Window should be open in the schedule timezone.');
    }

    public function testBetweenIsTimezoneAwareRegardlessOfCallOrder(): void
    {
        $zone = $this->timezoneOffsetFromServerBy(6);
        $remoteNow = new DateTimeImmutable('now', $zone);

        $start = $remoteNow->modify('-1 hour')->format('H:i');
        $end = $remoteNow->modify('+1 hour')->format('H:i');

        if ($start > $end) {
            $this->markTestSkipped('Window wraps midnight for the chosen timezone.');
        }

        // timezone() applied *after* between() must still be honoured.
        $schedule = (new Schedule('x'))->between($start, $end)->timezone($zone);

        $this->assertFalse($schedule->shouldSkip());
    }

    public function testBetweenExcludesTimesOutsideTheWindowInScheduleTimezone(): void
    {
        $zone = new DateTimeZone('UTC');
        $utcNow = new DateTimeImmutable('now', $zone);

        // A one-hour window that definitely does not contain "now" in UTC.
        $start = $utcNow->modify('+3 hours')->format('H:i');
        $end = $utcNow->modify('+4 hours')->format('H:i');

        if ($start > $end) {
            $this->markTestSkipped('Window wraps midnight.');
        }

        $schedule = (new Schedule('x'))->timezone($zone)->between($start, $end);

        $this->assertTrue($schedule->shouldSkip(), 'Task outside its window should be skipped.');
    }

    public function testUnlessBetweenUsesTheScheduleTimezone(): void
    {
        $zone = new DateTimeZone('UTC');
        $utcNow = new DateTimeImmutable('now', $zone);

        $start = $utcNow->modify('-1 hour')->format('H:i');
        $end = $utcNow->modify('+1 hour')->format('H:i');

        if ($start > $end) {
            $this->markTestSkipped('Window wraps midnight.');
        }

        $schedule = (new Schedule('x'))->timezone($zone)->unlessBetween($start, $end);

        // "now" is inside the blackout window, so the task must be skipped.
        $this->assertTrue($schedule->shouldSkip());
    }

    public function testBetweenWithoutTimezoneFallsBackToServerTime(): void
    {
        $now = new DateTimeImmutable('now');
        $start = $now->modify('-1 hour')->format('H:i');
        $end = $now->modify('+1 hour')->format('H:i');

        if ($start > $end) {
            $this->markTestSkipped('Window wraps midnight.');
        }

        $schedule = (new Schedule('x'))->between($start, $end);

        $this->assertFalse($schedule->shouldSkip());
    }

    public function testOvernightWindowStillWorks(): void
    {
        $zone = new DateTimeZone('UTC');
        $utcNow = new DateTimeImmutable('now', $zone);

        // A window that wraps midnight and contains "now".
        $start = $utcNow->modify('-1 hour')->format('H:i');
        $end = $utcNow->modify('-2 hours')->format('H:i');

        $schedule = (new Schedule('x'))->timezone($zone)->between($start, $end);

        $this->assertFalse($schedule->shouldSkip(), 'Overnight window containing now should be open.');
    }

    // --- frequency helpers must validate their input ---

    public static function outOfRangeFrequencies(): array
    {
        return [
            'hourlyAt minute 60' => [fn() => (new Schedule('x'))->hourlyAt(60), 'minute'],
            'hourlyAt negative' => [fn() => (new Schedule('x'))->hourlyAt(-1), 'minute'],
            'dailyAt hour 24' => [fn() => (new Schedule('x'))->dailyAt(24), 'hour'],
            'dailyAt hour 99' => [fn() => (new Schedule('x'))->dailyAt(99, 99), 'hour'],
            'dailyAt minute 60' => [fn() => (new Schedule('x'))->dailyAt(9, 60), 'minute'],
            'twiceDaily second hour' => [fn() => (new Schedule('x'))->twiceDaily(1, 25), 'hour'],
            'weeklyOn day 8' => [fn() => (new Schedule('x'))->weeklyOn(8), 'day of week'],
            'monthlyOn day 0' => [fn() => (new Schedule('x'))->monthlyOn(0), 'day of month'],
            'monthlyOn day 32' => [fn() => (new Schedule('x'))->monthlyOn(32), 'day of month'],
        ];
    }

    #[DataProvider('outOfRangeFrequencies')]
    public function testOutOfRangeFrequencyValuesAreRejectedAtTheCallSite(
        callable $factory,
        string $expectedLabel,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedLabel);

        $factory();
    }

    public static function validFrequencies(): array
    {
        return [
            'hourlyAt 0' => [fn() => (new Schedule('x'))->hourlyAt(0), '0 * * * *'],
            'hourlyAt 59' => [fn() => (new Schedule('x'))->hourlyAt(59), '59 * * * *'],
            'dailyAt 0' => [fn() => (new Schedule('x'))->dailyAt(0), '0 0 * * *'],
            'dailyAt 23:59' => [fn() => (new Schedule('x'))->dailyAt(23, 59), '59 23 * * *'],
            'weeklyOn sunday-7' => [fn() => (new Schedule('x'))->weeklyOn(7), '0 0 * * 7'],
            'weeklyOn sunday-0' => [fn() => (new Schedule('x'))->weeklyOn(0), '0 0 * * 0'],
            'monthlyOn 1' => [fn() => (new Schedule('x'))->monthlyOn(1), '0 0 1 * *'],
            'monthlyOn 31' => [fn() => (new Schedule('x'))->monthlyOn(31), '0 0 31 * *'],
            'twiceDaily bounds' => [fn() => (new Schedule('x'))->twiceDaily(0, 23), '0 0,23 * * *'],
        ];
    }

    #[DataProvider('validFrequencies')]
    public function testBoundaryFrequencyValuesAreAccepted(callable $factory, string $expected): void
    {
        /** @var Schedule $schedule */
        $schedule = $factory();

        $this->assertSame($expected, $schedule->getExpression());
        // The expression must also be parseable, not merely well-formatted.
        $this->assertIsBool($schedule->isDue('now'));
    }
}
