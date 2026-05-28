<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\OutputFormat;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class OutputFormatTest extends TestCase
{
    private function createCommand(): Command
    {
        return new class extends Command {
            use OutputFormat;

            static string $name = 'test:format';
            static string $description = 'Format test';

            protected function init(): void
            {
                $this->addFormatOption();
            }

            protected function handle(): void
            {
                $this->outputFormatted(
                    ['Name', 'Score'],
                    [
                        ['Alice', '100'],
                        ['Bob', '95'],
                    ]
                );
            }
        };
    }

    private function runCommand(array $input = []): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($this->createCommand());

        $arrayInput = new ArrayInput(array_merge(['command' => 'test:format'], $input));
        $output = new BufferedOutput();
        $app->doRun($arrayInput, $output);

        return $output->fetch();
    }

    public function testDefaultFormatIsTable(): void
    {
        $output = $this->runCommand();
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('Bob', $output);
        // Table format has borders
        $this->assertStringContainsString('---', $output);
    }

    public function testJsonFormat(): void
    {
        $output = $this->runCommand(['--format' => 'json']);
        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertSame('Alice', $data[0]['Name']);
        $this->assertSame('100', $data[0]['Score']);
        $this->assertSame('Bob', $data[1]['Name']);
    }

    public function testCsvFormat(): void
    {
        $output = $this->runCommand(['--format' => 'csv']);
        $this->assertStringContainsString('Name,Score', $output);
        $this->assertStringContainsString('Alice,100', $output);
        $this->assertStringContainsString('Bob,95', $output);
    }

    public function testJsonFormatWithAssociativeRows(): void
    {
        $command = new class extends Command {
            use OutputFormat;

            static string $name = 'test:format-assoc';
            static string $description = 'Assoc format test';

            protected function init(): void
            {
                $this->addFormatOption();
            }

            protected function handle(): void
            {
                $this->outputFormatted(
                    ['Name', 'Score'],
                    [
                        ['Name' => 'Charlie', 'Score' => '88'],
                    ]
                );
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:format-assoc', '--format' => 'json']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $data = json_decode($output->fetch(), true);
        $this->assertSame('Charlie', $data[0]['Name']);
        $this->assertSame('88', $data[0]['Score']);
    }

    public function testGetFormatReturnsLowercase(): void
    {
        $output = $this->runCommand(['--format' => 'JSON']);
        // Should still parse as JSON
        $data = json_decode($output, true);
        $this->assertIsArray($data);
    }

    public function testEmptyRows(): void
    {
        $command = new class extends Command {
            use OutputFormat;

            static string $name = 'test:format-empty';
            static string $description = 'Empty format test';

            protected function init(): void
            {
                $this->addFormatOption();
            }

            protected function handle(): void
            {
                $this->outputFormatted(['Name'], []);
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        // JSON empty
        $input = new ArrayInput(['command' => 'test:format-empty', '--format' => 'json']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->assertSame([], json_decode(trim($output->fetch()), true));

        // CSV empty (just headers)
        $input = new ArrayInput(['command' => 'test:format-empty', '--format' => 'csv']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->assertStringContainsString('Name', $output->fetch());
    }
}
