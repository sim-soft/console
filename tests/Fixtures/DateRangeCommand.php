<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;
use Simsoft\Console\Traits\DateRangeOption;

class DateRangeCommand extends Command
{
    use DateRangeOption;

    static string $name = 'test:date-range';
    static string $description = 'Command with date range options';

    protected function init(): void
    {
        $this->addDateRangeOption();
    }

    protected function handle(): void
    {
        [$fromDate, $toDate] = $this->dateRangeOption();

        if ($fromDate) {
            $this->line('FROM:' . $fromDate->format('Y-m-d'));
        }
        if ($toDate) {
            $this->line('TO:' . $toDate->format('Y-m-d'));
        }
        if (!$fromDate && !$toDate) {
            $this->line('NO_DATES');
        }
    }
}
