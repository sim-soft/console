<?php

namespace Simsoft\Console\Traits;

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
        return strtolower($this->option('format', 'table'));
    }

    /**
     * Output data in the selected format.
     *
     * @param array $headers Column headers.
     * @param array $rows Array of associative arrays or indexed arrays.
     * @return void
     */
    protected function outputFormatted(array $headers, array $rows): void
    {
        match ($this->getFormat()) {
            'json' => $this->outputJson($headers, $rows),
            'csv' => $this->outputCsv($headers, $rows),
            default => $this->outputTable($headers, $rows),
        };
    }

    /**
     * Output as table.
     *
     * @param array $headers
     * @param array $rows
     * @return void
     */
    private function outputTable(array $headers, array $rows): void
    {
        $this->table($headers, $rows);
    }

    /**
     * Output as JSON.
     *
     * @param array $headers
     * @param array $rows
     * @return void
     */
    private function outputJson(array $headers, array $rows): void
    {
        $data = array_map(function (array $row) use ($headers) {
            if (array_is_list($row)) {
                return array_combine($headers, $row);
            }
            return $row;
        }, $rows);

        $this->output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Output as CSV.
     *
     * @param array $headers
     * @param array $rows
     * @return void
     */
    private function outputCsv(array $headers, array $rows): void
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers, ',', '"', '');

        foreach ($rows as $row) {
            if (!array_is_list($row)) {
                $row = array_values($row);
            }
            fputcsv($stream, $row, ',', '"', '');
        }

        rewind($stream);
        $this->output->write(stream_get_contents($stream));
        fclose($stream);
    }
}
