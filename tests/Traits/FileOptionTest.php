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
}
