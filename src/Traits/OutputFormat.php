<?php

namespace Simsoft\Console\Traits;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;

/**
 * Trait OutputFormat
 *
 * Adds a --format option to switch between table, JSON, and CSV output.
 *
 * @method static addOption(string $name, string|array<int, string>|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array<int|string, string>|\Closure $suggestedValues = [])
 * @method mixed option(string $name, mixed $default = null)
 * @method void table(array<int, string> $headers, iterable<array-key, mixed> $data, ?callable $closure = null)
 * @method void line(string $message)
 */
trait OutputFormat
{
    /**
     * Add the --format option.
     *
     * @param string $default Default format (table, json, csv).
     * @return void
     */
    protected function addFormatOption(string $default = 'table'): void
    {
        $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            'Output format: table, json, csv',
            $default,
        );
    }

    /**
     * Get the selected output format.
     *
     * @return string
     */
    protected function getFormat(): string
    {
        $format = $this->option('format', 'table');

        // option() is mixed: --format is VALUE_REQUIRED, but a repeated flag or
        // a hand-built ArrayInput can put an array here, which strtolower()
        // rejects with a TypeError from inside this trait.
        if (!is_string($format)) {
            throw new InvalidArgumentException(sprintf(
                'The --format option must be a string, got %s.',
                get_debug_type($format)
            ));
        }

        return strtolower($format);
    }

    /**
     * Output data in the selected format.
     *
     * @param array<int, string> $headers Column headers.
     * @param array<array-key, mixed> $rows Array of associative arrays or indexed arrays.
     * @return void
     * @throws InvalidArgumentException If the format is not one of table, json, csv.
     */
    protected function outputFormatted(array $headers, array $rows): void
    {
        $format = $this->getFormat();

        // An unrecognised format fell through to the table. `--format=jsonn`
        // exited 0 and printed a table, so a pipeline parsing the output got
        // box-drawing characters where it expected JSON, and the typo looked
        // like a downstream bug.
        match ($format) {
            'json' => $this->outputJson($headers, $rows),
            'csv' => $this->outputCsv($headers, $rows),
            'table' => $this->outputTable($headers, $rows),
            default => throw new InvalidArgumentException(
                "Unknown output format \"$format\". Expected one of: table, json, csv."
            ),
        };
    }

    /**
     * Output as table.
     *
     * @param array<int, string> $headers
     * @param array<array-key, mixed> $rows
     * @return void
     */
    private function outputTable(array $headers, array $rows): void
    {
        $this->table($headers, $rows);
    }

    /**
     * Output as JSON.
     *
     * @param array<int, string> $headers
     * @param array<array-key, mixed> $rows
     * @return void
     * @throws InvalidArgumentException If a row is not an array.
     * @throws JsonException If the data cannot be encoded.
     */
    private function outputJson(array $headers, array $rows): void
    {
        $data = [];

        foreach ($rows as $index => $row) {
            $data[] = array_is_list($this->assertRow($row, $index))
                ? array_combine($headers, $row)
                : $row;
        }

        // json_encode() returns false rather than throwing, and writeln() cast
        // that to an empty string: a row carrying invalid UTF-8 — the usual
        // source is a database column in another encoding — printed one blank
        // line and exited 0. A consumer read that as "no results" instead of
        // "the export failed", which is the worse of the two by far.
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->output->writeln($json);
    }

    /**
     * Output as CSV.
     *
     * @param array<int, string> $headers
     * @param array<array-key, mixed> $rows
     * @return void
     * @throws InvalidArgumentException If a row is not an array.
     */
    private function outputCsv(array $headers, array $rows): void
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary stream to build the CSV.');
        }

        fputcsv($stream, $headers, ',', '"', '');

        foreach ($rows as $index => $row) {
            $row = $this->assertRow($row, $index);

            fputcsv($stream, array_is_list($row) ? $row : array_values($row), ',', '"', '');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $this->output->write($contents === false ? '' : $contents);
    }

    /**
     * Verify a row is an array before it is formatted.
     *
     * A scalar row reached array_is_list() or the json closure and produced a
     * TypeError naming an internal function or a closure — neither of which
     * says which row was wrong, and both of which read as a bug in this package
     * rather than in the data passed to it.
     *
     * @param mixed $row
     * @param array-key $index Named in the error.
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException If the row is not an array.
     */
    private function assertRow(mixed $row, string|int $index): array
    {
        if (!is_array($row)) {
            throw new InvalidArgumentException(sprintf(
                'Row "%s" is %s; outputFormatted() expects each row to be an array.',
                $index,
                get_debug_type($row)
            ));
        }

        return $row;
    }
}
