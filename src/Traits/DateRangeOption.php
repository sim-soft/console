<?php

namespace Simsoft\Console\Traits;

use DateTimeImmutable;
use Exception;
use Symfony\Component\Console\Input\InputOption;

/**
 * Optional date range trait.
 *
 * @method static addOption(string $name, string|array<int, string>|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array<int|string, string>|\Closure $suggestedValues = [])
 * @method mixed option(string $name, mixed $default = null)
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
        string $monthName = 'month',
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
        string $monthName = 'month',
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
            $monthDT = $this->parseStrictDate("$month-01", 'Y-m-d');
            !$monthDT && throw new Exception($monthError);
            $from = $monthDT->format('Y-m-01');
            $to = $monthDT->format('Y-m-t');
        }

        if (!$month) {
            $from = $this->option($fromDateName, $defaultFromDate);
            $to = $this->option($toDateName, $defaultToDate);
        }

        if ($from) {
            $fromDate = $this->parseStrictDate($from, 'Y-m-d');
            !$fromDate && throw new Exception($fromDateError);
        }

        if ($to) {
            $toDate = $this->parseStrictDate($to, 'Y-m-d');
            !$toDate && throw new Exception($toDateError);
        }

        $fromDate != null && $toDate != null && $fromDate > $toDate
            && throw new Exception($toDateIsLargerError);

        return [$fromDate, $toDate];
    }

    /**
     * Parse a date, rejecting values PHP would otherwise roll over.
     *
     * date_create_immutable_from_format() happily turns '2026-13-45' into
     * '2027-02-14'. Re-formatting the result and comparing it to the input
     * rejects any value that was not already a valid date.
     *
     * The leading '!' resets fields the format does not name, so a date-only
     * value parses to midnight instead of the current clock time. Without it a
     * range boundary carried the time of day the command ran: --to-date given a
     * day excluded records from that morning when the report ran at 06:00 but
     * included them at 18:00, so the same command over the same data returned
     * different rows depending on when cron fired.
     *
     * @param string $value The raw input value.
     * @param string $format Expected date format.
     * @return DateTimeImmutable|null Null when the value is not a valid date.
     */
    protected function parseStrictDate(string $value, string $format = 'Y-m-d'): ?DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        // Compared against the original format: '!' is a parsing instruction
        // and never appears in output.
        if ($date === false || $date->format($format) !== $value) {
            return null;
        }

        return $date;
    }
}
