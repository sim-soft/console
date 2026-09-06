<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\FileOptionCommand;

class FileOptionTest extends TestCase
{
    private function runFileOption(array $options = []): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new FileOptionCommand());

        $input = new ArrayInput(array_merge(['command' => 'test:file-option'], $options));
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        return $output->fetch();
    }

    // --- No file option ---

    public function testNoFileOptionReturnsNull(): void
    {
        $output = $this->runFileOption();
        $this->assertStringContainsString('NULL', $output);
    }

    // --- Single file ---

    public function testSingleFile(): void
    {
        $output = $this->runFileOption(['--file' => 'report.xlsx']);
        $this->assertStringContainsString('SINGLE:report.xlsx', $output);
    }

    public function testSingleFileWithQuotes(): void
    {
        $output = $this->runFileOption(['--file' => "'report.xlsx'"]);
        $this->assertStringContainsString('SINGLE:report.xlsx', $output);
    }

    public function testSingleFileWithDoubleQuotes(): void
    {
        $output = $this->runFileOption(['--file' => '"report.xlsx"']);
        $this->assertStringContainsString('SINGLE:report.xlsx', $output);
    }

    public function testSingleFileWithSpaces(): void
    {
        $output = $this->runFileOption(['--file' => '  report.xlsx  ']);
        $this->assertStringContainsString('SINGLE:report.xlsx', $output);
    }

    // --- Multiple files ---

    public function testMultipleFiles(): void
    {
        $output = $this->runFileOption(['--file' => 'file1.xlsx,file2.xlsx,file3.xlsx']);
        $this->assertStringContainsString('MULTI:file1.xlsx|file2.xlsx|file3.xlsx', $output);
    }

    public function testMultipleFilesWithSpaces(): void
    {
        $output = $this->runFileOption(['--file' => 'file1.xlsx , file2.xlsx , file3.xlsx']);
        $this->assertStringContainsString('MULTI:file1.xlsx|file2.xlsx|file3.xlsx', $output);
    }

    // --- File extension ---

    public function testSingleFileWithExtension(): void
    {
        $output = $this->runFileOption(['--file' => 'report']);
        $this->assertStringContainsString('EXT:report.csv', $output);
    }

    public function testMultipleFilesWithExtension(): void
    {
        $output = $this->runFileOption(['--file' => 'file1,file2,file3']);
        $this->assertStringContainsString('MULTI_EXT:file1.xlsx|file2.xlsx|file3.xlsx', $output);
    }

    // --- Edge cases ---

    public function testSingleCommaProducesEmptyFilteredArray(): void
    {
        $output = $this->runFileOption(['--file' => ',']);
        // explode(',', ',') gives ['', ''] - after trim and filter, should be empty or have entries
        $this->assertStringContainsString('MULTI:', $output);
    }

    public function testFileWithPath(): void
    {
        $output = $this->runFileOption(['--file' => '/path/to/report.xlsx']);
        $this->assertStringContainsString('SINGLE:/path/to/report.xlsx', $output);
    }

    // --- Empty entries must not become filenames ---

    public function testEmptyValueDoesNotProduceAFileNamedAfterTheExtension(): void
    {
        // The extension was appended unconditionally and array_filter() kept
        // the result because '.xlsx' is truthy, so an empty --file yielded a
        // list containing one file nobody had named.
        $output = $this->runFileOption(['--file' => '']);

        $this->assertStringContainsString('MULTI_EXT:' . PHP_EOL, $output);
        $this->assertStringNotContainsString('MULTI_EXT:.xlsx', $output);
    }

    public function testEmptyValueYieldsNoSingleFile(): void
    {
        $output = $this->runFileOption(['--file' => '']);

        $this->assertStringContainsString('NULL', $output);
        $this->assertStringNotContainsString('EXT:.csv', $output);
    }

    public function testEmptySegmentBetweenTwoFilesIsDropped(): void
    {
        // '--file=a,,b' used to yield a phantom '.xlsx' between the two files.
        $output = $this->runFileOption(['--file' => 'a,,b']);

        $this->assertStringContainsString('MULTI_EXT:a.xlsx|b.xlsx', $output);
    }

    public function testWhitespaceOnlySegmentsAreDropped(): void
    {
        $output = $this->runFileOption(['--file' => ' , ']);

        $this->assertStringContainsString('MULTI_EXT:' . PHP_EOL, $output);
        $this->assertStringNotContainsString('.xlsx', $output);
    }

    public function testTrailingCommaIsDropped(): void
    {
        $output = $this->runFileOption(['--file' => 'a.xlsx,b.xlsx,']);

        $this->assertStringContainsString('MULTI:a.xlsx|b.xlsx', $output);
    }

    public function testNonStringValueIsRejectedWithAClearMessage(): void
    {
        // Rejected rather than reaching trim() and raising a TypeError from
        // inside the trait. Booleans are not a documented input shape; arrays
        // are, and are joined instead (see below).
        $output = $this->runFileOption(['--file' => true]);

        $this->assertStringContainsString('--file option must be a string', $output);
    }

    public function testArrayDefaultIsTreatedAsACommaSeparatedList(): void
    {
        // string[] is a documented shape for $default, so it can arrive here
        // whenever the option is absent from the command line.
        $command = new class extends \Simsoft\Console\Command {
            use \Simsoft\Console\Traits\FileOption;

            static string $name = 'test:file-array-default';
            static string $description = 'Array default file option';

            protected function init(): void
            {
                $this->addFileOption();
            }

            protected function handle(): void
            {
                $files = $this->fileOption('file', ['a', 'b'], true, 'xlsx');
                $this->line('MULTI_EXT:' . implode('|', $files));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $app->doRun(new ArrayInput(['command' => 'test:file-array-default']), $output);

        $this->assertStringContainsString('MULTI_EXT:a.xlsx|b.xlsx', $output->fetch());
    }
}
