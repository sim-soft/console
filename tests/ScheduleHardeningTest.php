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
     * A timezone whose current local hour falls inside the given band.
     *
     * These tests build their windows by adding and subtracting hours from
     * "now". A zone whose clock currently sits near midnight produces a window
     * that wraps, which most of these assertions cannot express — so they used
     * to skip themselves, silently, for a few hours out of every day. Choosing
     * the zone by where its clock stands instead keeps the window inside one
     * day whatever time the suite runs.
     *
     * Offsets run from -11 to +14, so every hour of the clock is somebody's
     * local hour and any band this file asks for is satisfiable.
     */
    private function zoneWithLocalHourBetween(int $minHour, int $maxHour): DateTimeZone
    {
        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $zone = new DateTimeZone($identifier);
            $hour = (int)(new DateTimeImmutable('now', $zone))->format('G');

            if ($hour >= $minHour && $hour <= $maxHour) {
                return $zone;
            }
        }

        // Not a skip: every hour is covered by some zone, so reaching here
        // means the assumption above no longer holds and the tests relying on
        // it are not testing what they claim.
        self::fail("No timezone found whose local hour is between $minHour and $maxHour.");
    }

    /**
     * A one-hour-either-side window around "now" in some timezone, chosen so
     * that the server's own current time falls *outside* it.
     *
     * Both properties matter. Without the first the window wraps midnight;
     * without the second a timezone-blind implementation passes by
     * coincidence, which is the bug these tests exist to catch.
     *
     * @return array{DateTimeZone, string, string} Zone, window start, window end.
     */
    private function remoteWindowExcludingServerTime(): array
    {
        $serverNow = (new DateTimeImmutable('now'))->format('H:i');

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $zone = new DateTimeZone($identifier);
            $remoteNow = new DateTimeImmutable('now', $zone);

            $start = $remoteNow->modify('-1 hour')->format('H:i');
            $end = $remoteNow->modify('+1 hour')->format('H:i');

            if ($start > $end) {
                continue;
            }

            if ($serverNow >= $start && $serverNow <= $end) {
                continue;
            }

            return [$zone, $start, $end];
        }

        self::fail('No timezone found whose current window excludes the server time.');
    }

    // --- between() must honour the schedule timezone ---

    public function testBetweenUsesTheScheduleTimezone(): void
    {
        // A window around "now" in a zone whose clock differs enough from the
        // server's that the server time falls outside the window.
        [$zone, $start, $end] = $this->remoteWindowExcludingServerTime();

        $schedule = (new Schedule('x'))->timezone($zone)->between($start, $end);

        // The window brackets "now" in the remote zone but not on the server.
        $this->assertFalse($schedule->shouldSkip(), 'Window should be open in the schedule timezone.');
    }

    public function testBetweenIsTimezoneAwareRegardlessOfCallOrder(): void
    {
        [$zone, $start, $end] = $this->remoteWindowExcludingServerTime();

        // timezone() applied *after* between() must still be honoured.
        $schedule = (new Schedule('x'))->between($start, $end)->timezone($zone);

        $this->assertFalse($schedule->shouldSkip());
    }

    public function testBetweenExcludesTimesOutsideTheWindowInScheduleTimezone(): void
    {
        // Local hour 0-19 leaves room for the +3/+4 hour window below to stay
        // inside the same day.
        $zone = $this->zoneWithLocalHourBetween(0, 19);
        $now = new DateTimeImmutable('now', $zone);

        // A one-hour window that definitely does not contain "now".
        $start = $now->modify('+3 hours')->format('H:i');
        $end = $now->modify('+4 hours')->format('H:i');

        $schedule = (new Schedule('x'))->timezone($zone)->between($start, $end);

        $this->assertTrue($schedule->shouldSkip(), 'Task outside its window should be skipped.');
    }

    public function testUnlessBetweenUsesTheScheduleTimezone(): void
    {
        // Local hour 1-22 keeps the one-hour-either-side window off midnight.
        $zone = $this->zoneWithLocalHourBetween(1, 22);
        $now = new DateTimeImmutable('now', $zone);

        $start = $now->modify('-1 hour')->format('H:i');
        $end = $now->modify('+1 hour')->format('H:i');

        $schedule = (new Schedule('x'))->timezone($zone)->unlessBetween($start, $end);

        // "now" is inside the blackout window, so the task must be skipped.
        $this->assertTrue($schedule->shouldSkip());
    }

    public function testBetweenWithoutTimezoneFallsBackToServerTime(): void
    {
        // This one is about the server clock specifically, so the zone cannot
        // be chosen — move the server clock instead, and put it at midday so
        // the window cannot wrap. tearDown() restores it.
        date_default_timezone_set($this->zoneWithLocalHourBetween(1, 22)->getName());

        $now = new DateTimeImmutable('now');
        $start = $now->modify('-1 hour')->format('H:i');
        $end = $now->modify('+1 hour')->format('H:i');

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
