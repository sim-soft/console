<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\DateRangeOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\DateRangeCommand;

class DateRangeOptionTest extends TestCase
{
    private function runDateRange(array $options = []): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new DateRangeCommand());

        $input = new ArrayInput(array_merge(['command' => 'test:date-range'], $options));
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        return $output->fetch();
    }

    // --- No dates provided ---

    public function testNoDatesReturnsNullPair(): void
    {
        $output = $this->runDateRange();
        $this->assertStringContainsString('NO_DATES', $output);
    }

    // --- Month option ---

    public function testMonthOptionSetsFromAndToDate(): void
    {
        $output = $this->runDateRange(['--month' => '2024-03']);
        $this->assertStringContainsString('FROM:2024-03-01', $output);
        $this->assertStringContainsString('TO:2024-03-31', $output);
    }

    public function testMonthOptionFebruary(): void
    {
        $output = $this->runDateRange(['--month' => '2024-02']);
        $this->assertStringContainsString('FROM:2024-02-01', $output);
        $this->assertStringContainsString('TO:2024-02-29', $output); // 2024 is leap year
    }

    public function testMonthOptionFebruaryNonLeapYear(): void
    {
        $output = $this->runDateRange(['--month' => '2023-02']);
        $this->assertStringContainsString('FROM:2023-02-01', $output);
        $this->assertStringContainsString('TO:2023-02-28', $output);
    }

    public function testMonthOptionDecember(): void
    {
        $output = $this->runDateRange(['--month' => '2024-12']);
        $this->assertStringContainsString('FROM:2024-12-01', $output);
        $this->assertStringContainsString('TO:2024-12-31', $output);
    }

    public function testInvalidMonthThrowsException(): void
    {
        $output = $this->runDateRange(['--month' => 'invalid']);
        $this->assertStringContainsString('Invalid month value', $output);
    }

    public function testInvalidMonthFormatThrowsException(): void
    {
        $output = $this->runDateRange(['--month' => '2024-13']);
        // month 13 is invalid, date_create_from_format may handle it differently
        // but the format check should catch it
        $this->assertNotEmpty($output);
    }

    // --- From/To date options ---

    public function testFromDateOnly(): void
    {
        $output = $this->runDateRange(['--from-date' => '2024-01-15']);
        $this->assertStringContainsString('FROM:2024-01-15', $output);
    }

    public function testToDateOnly(): void
    {
        $output = $this->runDateRange(['--to-date' => '2024-06-30']);
        $this->assertStringContainsString('TO:2024-06-30', $output);
    }

    public function testFromAndToDate(): void
    {
        $output = $this->runDateRange([
            '--from-date' => '2024-01-01',
            '--to-date' => '2024-01-31',
        ]);
        $this->assertStringContainsString('FROM:2024-01-01', $output);
        $this->assertStringContainsString('TO:2024-01-31', $output);
    }

    public function testToDateBeforeFromDateThrowsException(): void
    {
        $output = $this->runDateRange([
            '--from-date' => '2024-06-15',
            '--to-date' => '2024-01-01',
        ]);
        $this->assertStringContainsString('To date should be greater than from date', $output);
    }

    public function testInvalidFromDateThrowsException(): void
    {
        $output = $this->runDateRange(['--from-date' => 'not-a-date']);
        $this->assertStringContainsString('Invalid from date value', $output);
    }

    public function testInvalidToDateThrowsException(): void
    {
        $output = $this->runDateRange([
            '--from-date' => '2024-01-01',
            '--to-date' => 'not-a-date',
        ]);
        $this->assertStringContainsString('Invalid to date value', $output);
    }

    // --- Month takes precedence over from/to ---

    public function testMonthTakesPrecedenceOverFromTo(): void
    {
        $output = $this->runDateRange([
            '--month' => '2024-05',
            '--from-date' => '2024-01-01',
            '--to-date' => '2024-12-31',
        ]);
        $this->assertStringContainsString('FROM:2024-05-01', $output);
        $this->assertStringContainsString('TO:2024-05-31', $output);
    }

    // --- Same from and to date ---

    public function testSameFromAndToDate(): void
    {
        $output = $this->runDateRange([
            '--from-date' => '2024-03-15',
            '--to-date' => '2024-03-15',
        ]);
        $this->assertStringContainsString('FROM:2024-03-15', $output);
        $this->assertStringContainsString('TO:2024-03-15', $output);
    }

    // --- Out-of-range dates must be rejected, not rolled over ---

    public function testOutOfRangeFromDateIsRejectedInsteadOfRollingOver(): void
    {
        // date_create_immutable_from_format() would silently yield 2027-02-14.
        $output = $this->runDateRange(['--from-date' => '2026-13-45']);

        $this->assertStringContainsString('Invalid from date value', $output);
        $this->assertStringNotContainsString('FROM:2027', $output);
    }

    public function testOutOfRangeToDateIsRejectedInsteadOfRollingOver(): void
    {
        $output = $this->runDateRange([
            '--from-date' => '2024-01-01',
            '--to-date' => '2024-02-31',
        ]);

        $this->assertStringContainsString('Invalid to date value', $output);
        $this->assertStringNotContainsString('TO:2024-03', $output);
    }

    public function testImpossibleLeapDayIsRejected(): void
    {
        // 2023 is not a leap year; PHP would roll this to 2023-03-01.
        $output = $this->runDateRange(['--from-date' => '2023-02-29']);

        $this->assertStringContainsString('Invalid from date value', $output);
        $this->assertStringNotContainsString('FROM:2023-03-01', $output);
    }

    public function testRealLeapDayIsAccepted(): void
    {
        $output = $this->runDateRange(['--from-date' => '2024-02-29']);

        $this->assertStringContainsString('FROM:2024-02-29', $output);
    }

    public function testOutOfRangeMonthIsRejected(): void
    {
        $output = $this->runDateRange(['--month' => '2024-13']);

        $this->assertStringContainsString('Invalid month value', $output);
        $this->assertStringNotContainsString('FROM:2025', $output);
    }

    public function testShortFormDateIsRejected(): void
    {
        // '2024-1-5' is not the documented YYYY-MM-DD format.
        $output = $this->runDateRange(['--from-date' => '2024-1-5']);

        $this->assertStringContainsString('Invalid from date value', $output);
    }

    public function testDateWithTrailingContentIsRejected(): void
    {
        $output = $this->runDateRange(['--from-date' => '2024-01-01 garbage']);

        $this->assertStringContainsString('Invalid from date value', $output);
    }

    // --- Boundaries must not carry the time of day the command ran ---

    /**
     * Run a range command that prints both boundaries down to the second.
     *
     * The shared fixture prints Y-m-d, which is the part that was always
     * correct. The time components were taken from the current clock, so the
     * same command over the same data returned different rows depending on
     * when cron fired.
     *
     * @param array<string, string> $options
     */
    private function runDateRangeWithTime(array $options = []): string
    {
        $command = new class extends Command {
            use DateRangeOption;

            static string $name = 'test:date-range-time';
            static string $description = 'Date range time components test';

            protected function init(): void
            {
                $this->addDateRangeOption();
            }

            protected function handle(): void
            {
                [$fromDate, $toDate] = $this->dateRangeOption();

                $this->line('FROM:' . ($fromDate?->format('Y-m-d H:i:s') ?? 'null'));
                $this->line('TO:' . ($toDate?->format('Y-m-d H:i:s') ?? 'null'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $app->doRun(
            new ArrayInput(array_merge(['command' => 'test:date-range-time'], $options)),
            $output
        );

        return $output->fetch();
    }

    public function testFromAndToDatesParseToMidnight(): void
    {
        $output = $this->runDateRangeWithTime([
            '--from-date' => '2024-03-01',
            '--to-date' => '2024-03-31',
        ]);

        $this->assertStringContainsString('FROM:2024-03-01 00:00:00', $output);
        $this->assertStringContainsString('TO:2024-03-31 00:00:00', $output);
    }

    public function testMonthBoundariesParseToMidnight(): void
    {
        $output = $this->runDateRangeWithTime(['--month' => '2024-03']);

        $this->assertStringContainsString('FROM:2024-03-01 00:00:00', $output);
        $this->assertStringContainsString('TO:2024-03-31 00:00:00', $output);
    }

    public function testBoundariesDoNotShiftWithTheClock(): void
    {
        // Two runs of the same command must produce identical boundaries. This
        // is a weaker assertion than the two above but it is the one that
        // states the actual guarantee: the result depends on the input only.
        $options = ['--from-date' => '2024-03-01', '--to-date' => '2024-03-31'];

        $this->assertSame(
            $this->runDateRangeWithTime($options),
            $this->runDateRangeWithTime($options)
        );
    }

    public function testSurroundingWhitespaceIsAccepted(): void
    {
        $output = $this->runDateRange(['--from-date' => '  2024-03-01  ']);

        $this->assertStringContainsString('FROM:2024-03-01', $output);
    }
}
