<?php

namespace Simsoft\Console\Traits;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;

/**
 * Trait DateOption
 *
 * Adds a single --date option with validation and parsing.
 * Supports multiple date formats with auto-detection.
 * For date ranges, use DateRangeOption instead.
 *
 * @method static addOption(string $name, string|array<int, string>|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array<int|string, string>|\Closure $suggestedValues = [])
 * @method mixed option(string $name, mixed $default = null)
 */
trait DateOption
{
    /**
     * Add the --date option to the command.
     *
     * @param string $name Option name.
     * @param string $shortcut Option shortcut.
     * @param string $description Option description.
     * @param string|null $default Default value.
     * @return void
     */
    protected function addDateOption(
        string  $name = 'date',
        string  $shortcut = 'd',
        string  $description = 'Date. Format: YYYY-MM-DD.',
        ?string $default = null,
    ): void
    {
        $this->addOption(
            $name,
            $shortcut,
            InputOption::VALUE_REQUIRED,
            $description,
            $default,
        );
    }

    /**
     * Get the parsed date value.
     *
     * Accepts a single format string or an array of formats to try in order.
     * The first format that successfully parses the input is used.
     *
     * @param string $name Option name.
     * @param string|string[] $format Expected format(s). First match wins.
     * @param bool $defaultToday Use today's date if no value provided.
     * @param bool $required Throw if no value provided.
     * @param string $errorMessage Error message on invalid format. {format} is replaced.
     * @param string $requiredMessage Error message when required but not provided. {name} is replaced.
     * @return DateTimeImmutable|null Returns null only if not required, no value, and $defaultToday is false.
     * @throws InvalidArgumentException When required and not provided, or no format matches.
     */
    protected function dateOption(
        string       $name = 'date',
        string|array $format = 'Y-m-d',
        bool         $defaultToday = false,
        bool         $required = false,
        string       $errorMessage = 'Invalid date value. Expected format: {format}.',
        string       $requiredMessage = 'The --{name} option is required.',
    ): ?DateTimeImmutable
    {
        $value = $this->option($name);

        if ($value === null || $value === false) {
            if ($required) {
                throw new InvalidArgumentException(strtr($requiredMessage, ['{name}' => $name]));
            }
            if ($defaultToday) {
                return new DateTimeImmutable('today');
            }
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'The --%s option must be a string, got %s.',
                $name,
                get_debug_type($value)
            ));
        }

        $value = trim($value);
        $formats = (array)$format;

        foreach ($formats as $fmt) {
            // The leading '!' resets every field not named by the format to the
            // epoch. Without it createFromFormat() fills them from the current
            // clock, so --date=2024-06-15 parsed to 2024-06-15 at whatever time
            // the command happened to run — and a date-only format like 'Y-m'
            // took today's day of month as well. Anything comparing the result
            // against a timestamp then gave a different answer depending on the
            // hour, which is the kind of bug that reproduces only in the
            // afternoon. defaultToday already returned midnight, so the two
            // paths of this method also disagreed with each other.
            $date = DateTimeImmutable::createFromFormat('!' . $fmt, $value);

            // Compared against the original format: '!' is a parsing
            // instruction and never appears in output. A value PHP would
            // otherwise roll over ('2024-13-45' becoming '2025-02-14') fails
            // this and is rejected.
            if ($date && $date->format($fmt) === $value) {
                return $date;
            }
        }

        $formatList = implode(', ', $formats);
        throw new InvalidArgumentException(strtr($errorMessage, ['{format}' => $formatList]));
    }
}
