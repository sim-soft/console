<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\OutputFormat;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Regression tests for the OutputFormat trait:
 *  - json_encode() failing silently and printing a blank line
 *  - an unrecognised --format falling through to the table
 *  - a non-array row failing with a TypeError from an internal function
 */
class OutputFormatTest extends TestCase
{
    protected function setUp(): void
    {
        FormatProbeCommand::$headers = ['id', 'name'];
        FormatProbeCommand::$rows = [[1, 'ok']];
        Application::flushGlobal();
    }

    protected function tearDown(): void
    {
        Application::flushGlobal();
    }

    // --- the happy paths still work ---

    public function testJsonOutputIsWellFormed(): void
    {
        FormatProbeCommand::$rows = [[1, 'alice'], [2, 'bob']];

        ['status' => $status, 'output' => $output] = $this->runWithFormat('json');

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertSame(
            [['id' => 1, 'name' => 'alice'], ['id' => 2, 'name' => 'bob']],
            json_decode($output, true)
        );
    }

    public function testCsvOutputIsWellFormed(): void
    {
        FormatProbeCommand::$rows = [[1, 'alice']];

        ['status' => $status, 'output' => $output] = $this->runWithFormat('csv');

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('id,name', $output);
        $this->assertStringContainsString('1,alice', $output);
    }

    public function testTableIsTheDefault(): void
    {
        ['status' => $status, 'output' => $output] = $this->runWithFormat(null);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('| id', $output);
    }

    public function testFormatIsCaseInsensitive(): void
    {
        ['status' => $status, 'output' => $output] = $this->runWithFormat('JSON');

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertIsArray(json_decode($output, true));
    }

    // --- silent failures ---

    /**
     * The important one. json_encode() returns false rather than throwing, and
     * writeln() cast that to an empty string, so the command printed one blank
     * line and reported success. A consumer reads that as "no rows" rather
     * than "the export failed".
     */
    public function testUnencodableDataFailsInsteadOfPrintingNothing(): void
    {
        // Invalid UTF-8, as a database column in another encoding produces.
        FormatProbeCommand::$rows = [[1, "caf\xE9"]];

        ['status' => $status, 'output' => $output] = $this->runWithFormat('json');

        $this->assertSame(
            Command::FAILURE,
            $status,
            'Unencodable data must not be reported as a successful empty result.'
        );
        $this->assertStringContainsString('Malformed UTF-8', $output);
    }

    public function testUnencodableDataDoesNotEmitAnEmptyDocument(): void
    {
        FormatProbeCommand::$rows = [[1, "caf\xE9"]];

        ['output' => $output] = $this->runWithFormat('json');

        $this->assertNull(
            json_decode(trim($output)),
            'Nothing resembling a valid JSON document should have been written.'
        );
    }

    /**
     * A typo'd format silently produced a table, so a pipeline expecting JSON
     * received box-drawing characters and exit code 0.
     */
    public function testAnUnknownFormatIsRejected(): void
    {
        ['status' => $status, 'output' => $output] = $this->runWithFormat('jsonn');

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Unknown output format', $output);
        $this->assertStringContainsString('jsonn', $output);
    }

    public function testAnUnknownFormatDoesNotFallBackToATable(): void
    {
        ['output' => $output] = $this->runWithFormat('xml');

        $this->assertStringNotContainsString('| id', $output);
    }

    // --- unhelpful errors ---

    public function testANonArrayRowIsNamedRatherThanFailingInsideAnInternalFunction(): void
    {
        FormatProbeCommand::$rows = [[1, 'ok'], 'not-a-row'];

        ['status' => $status, 'output' => $output] = $this->runWithFormat('csv');

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Row "1"', $output);
        $this->assertStringContainsString('string', $output);
        $this->assertStringNotContainsString('array_is_list', $output);
    }

    public function testANonArrayRowIsAlsoRejectedByTheJsonPath(): void
    {
        FormatProbeCommand::$rows = [42];

        ['status' => $status, 'output' => $output] = $this->runWithFormat('json');

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Row "0"', $output);
        $this->assertStringNotContainsString('closure', $output);
    }

    public function testANonStringFormatIsRejected(): void
    {
        ['status' => $status, 'output' => $output] = $this->runWithFormat(['json', 'csv']);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('--format option must be a string', $output);
    }

    /**
     * Associative rows are keyed by the caller, not by $headers.
     */
    public function testAssociativeRowsArePassedThroughUnchanged(): void
    {
        FormatProbeCommand::$rows = [['name' => 'alice', 'id' => 1]];

        ['output' => $output] = $this->runWithFormat('json');

        $this->assertSame([['name' => 'alice', 'id' => 1]], json_decode($output, true));
    }

    /**
     * Run the probe command with the given --format and capture the result.
     *
     * @param mixed $format Null omits the option entirely.
     * @return array{status: int, output: string}
     */
    private function runWithFormat(mixed $format): array
    {
        $application = Application::make('test', '1.0');
        $application->setAutoExit(false);
        $application->withCommands([FormatProbeCommand::class]);

        $output = new BufferedOutput();
        $status = $application->doRun(
            Application::programmaticInput(
                'probe:format',
                $format === null ? [] : ['--format' => $format]
            ),
            $output
        );

        return ['status' => $status, 'output' => $output->fetch()];
    }
}

/**
 * Renders whatever the test sets on it through the trait.
 */
class FormatProbeCommand extends Command
{
    use OutputFormat;

    public static string $name = 'probe:format';

    /** @var array<int, string> */
    public static array $headers = ['id', 'name'];

    /** @var array<array-key, mixed> */
    public static array $rows = [];

    protected bool $messageTimeStamp = false;

    protected function init(): void
    {
        $this->addFormatOption();
    }

    protected function handle(): void
    {
        $this->outputFormatted(self::$headers, self::$rows);
    }
}
