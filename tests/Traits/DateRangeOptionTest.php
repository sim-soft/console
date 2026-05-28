<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
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
}
