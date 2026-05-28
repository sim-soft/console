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
 * @method addOption(string $name, string|array|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggestedValues = []): static
 * @method option(string $name, mixed $default = null): mixed
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

        $formats = (array)$format;

        foreach ($formats as $fmt) {
            $date = DateTimeImmutable::createFromFormat($fmt, $value);

            if ($date && $date->format($fmt) === $value) {
                return $date;
            }
        }

        $formatList = implode(', ', $formats);
        throw new InvalidArgumentException(strtr($errorMessage, ['{format}' => $formatList]));
    }
}
