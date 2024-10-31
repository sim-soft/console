<?php

namespace Simsoft\Console\Traits;

use DateTimeImmutable;
use Exception;
use Symfony\Component\Console\Input\InputOption;

/**
 * Optional date range trait.
 */
trait DateRangeOption
{
    /**
     * Configure optional date range options.
     *
     * @param string $monthName Month input name.
     * @param string $fromDateName From date input name.
     * @param string $toDateName To date input name.
     * @param string $monthShortcut Month shortcut.
     * @param string $fromDateShortcut From date shortcut.
     * @param string $toDateShortcut To date shortcut.
     * @param string $monthDescription Month input description.
     * @param string $fromDateDescription From date input description.
     * @param string $toDateDescription To date input description.
     * @param string|null $defaultMonth Default month value.
     * @param string|null $defaultFromDate Default from date value.
     * @param string|null $defaultToDate Default to date value.
     * @return void
     */
    protected function addDateRangeOption(
        string $monthName = 'for-month',
        string $fromDateName = 'from-date',
        string $toDateName = 'to-date',

        string $monthShortcut = 'm',
        string $fromDateShortcut = 'd',
        string $toDateShortcut = 't',

        string $monthDescription = 'For the specific month. Format: YYYY-MM.',
        string $fromDateDescription = 'From date. Format: YYYY-MM-DD.',
        string $toDateDescription = 'From date. Format: YYYY-MM-DD.',

        ?string $defaultMonth = null,
        ?string $defaultFromDate = null,
        ?string $defaultToDate = null,
    ): void
    {
        $this
            ->addOption(
                $monthName,
                $monthShortcut,
                InputOption::VALUE_REQUIRED,
                $monthDescription,
                $defaultMonth
            )->addOption(
                $fromDateName,
                $fromDateShortcut,
                InputOption::VALUE_REQUIRED,
                $fromDateDescription,
                $defaultFromDate
            )->addOption(
                $toDateName,
                $toDateShortcut,
                InputOption::VALUE_REQUIRED,
                $toDateDescription,
                $defaultToDate
            );
    }

    /**
     * Get optional date ranges.
     *
     * @param string $monthName Month input name.
     * @param string $fromDateName From date input name.
     * @param string $toDateName To date input name.
     * @param string|null $defaultMonth Default month value.
     * @param string|null $defaultFromDate Default from date value.
     * @param string|null $defaultToDate Default to date value.
     * @param string $monthError Month input error message.
     * @param string $fromDateError From date input error message.
     * @param string $toDateError To date input error message.
     * @param string $toDateIsLargerError To date larger than from date error message.
     * @return DateTimeImmutable[]|null[] [fromDate, toDate]
     * @throws Exception
     */
    protected function dateRangeOption(
        string $monthName = 'for-month',
        string $fromDateName = 'from-date',
        string $toDateName = 'to-date',

        ?string $defaultMonth = null,
        ?string $defaultFromDate = null,
        ?string $defaultToDate = null,

        string $monthError = 'Invalid month value. expected format: YYYY-MM.',
        string $fromDateError = 'Invalid from date value. expected format: YYYY-MM-DD.',
        string $toDateError = 'Invalid to date value. expected format: YYYY-MM-DD.',
        string $toDateIsLargerError = 'To date should be greater than from date.',
    ): array
    {
        $fromDate = $toDate = null;

        $month = $this->option($monthName, $defaultMonth);
        if ($month) {
            $monthDT = date_create_from_format('Y-m-d', "$month-01");
            !$monthDT && throw new Exception($monthError);
            $from = $monthDT->format('Y-m-01');
            $to = $monthDT->format('Y-m-t');
        } else {
            $from = $this->option($fromDateName, $defaultFromDate);
            $to = $this->option($toDateName, $defaultToDate);
        }

        if ($from) {
            $fromDate = date_create_immutable_from_format('Y-m-d', $from);
            !$fromDate && throw new Exception($fromDateError);
        }

        if ($to) {
            $toDate = date_create_immutable_from_format('Y-m-d', $to);
            !$toDate && throw new Exception($toDateError);
        }

        $toDate != null && $fromDate > $toDate && throw new Exception($toDateIsLargerError);

        return [$fromDate, $toDate];
    }
}
