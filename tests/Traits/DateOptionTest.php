<?php

declare(strict_types=1);

namespace Tests\Traits;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\DateOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DateOptionTest extends TestCase
{
    private function createCommand(): Command
    {
        return new class extends Command {
            use DateOption;

            static string $name = 'test:date';
            static string $description = 'Date option test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption();
                if ($date) {
                    $this->line('DATE:' . $date->format('Y-m-d'));
                } else {
                    $this->line('DATE:null');
                }
            }
        };
    }

    private function runCommand(array $input = []): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($this->createCommand());

        $arrayInput = new ArrayInput(array_merge(['command' => 'test:date'], $input));
        $output = new BufferedOutput();
        $app->doRun($arrayInput, $output);

        return $output->fetch();
    }

    // --- Basic usage ---

    public function testValidDate(): void
    {
        $output = $this->runCommand(['--date' => '2024-06-15']);
        $this->assertStringContainsString('DATE:2024-06-15', $output);
    }

    public function testNoDateReturnsNull(): void
    {
        $output = $this->runCommand();
        $this->assertStringContainsString('DATE:null', $output);
    }

    public function testInvalidDateThrowsException(): void
    {
        $output = $this->runCommand(['--date' => 'not-a-date']);
        $this->assertStringContainsString('Invalid date value', $output);
    }

    public function testInvalidDateFormatThrowsException(): void
    {
        $output = $this->runCommand(['--date' => '15-06-2024']);
        $this->assertStringContainsString('Invalid date value', $output);
    }

    // --- Default to today ---

    public function testDefaultToday(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-today';
            static string $description = 'Date today test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(defaultToday: true);
                $this->line('DATE:' . $date->format('Y-m-d'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-today']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('DATE:' . date('Y-m-d'), $output->fetch());
    }

    // --- Custom format ---

    public function testCustomInputFormat(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-custom';
            static string $description = 'Custom format test';

            protected function init(): void
            {
                $this->addDateOption(description: 'Date. Format: DD/MM/YYYY.');
            }

            protected function handle(): void
            {
                $date = $this->dateOption(format: 'd/m/Y');
                $this->line('DATE:' . ($date ? $date->format('Y-m-d') : 'null'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-custom', '--date' => '25/12/2024']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('DATE:2024-12-25', $output->fetch());
    }

    // --- Edge cases ---

    public function testLeapYearDate(): void
    {
        $output = $this->runCommand(['--date' => '2024-02-29']);
        $this->assertStringContainsString('DATE:2024-02-29', $output);
    }

    public function testInvalidLeapYearDate(): void
    {
        $output = $this->runCommand(['--date' => '2023-02-29']);
        $this->assertStringContainsString('Invalid date value', $output);
    }

    public function testEndOfMonth(): void
    {
        $output = $this->runCommand(['--date' => '2024-01-31']);
        $this->assertStringContainsString('DATE:2024-01-31', $output);
    }

    // --- Multi-format detection ---

    public function testMultiFormatDetectsIsoFormat(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-multi';
            static string $description = 'Multi format test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(format: ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Ymd']);
                $this->line('DATE:' . ($date ? $date->format('Y-m-d') : 'null'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-multi', '--date' => '2024-06-15']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('DATE:2024-06-15', $output->fetch());
    }

    public function testMultiFormatDetectsSlashFormat(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-multi-slash';
            static string $description = 'Multi format slash test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(format: ['Y-m-d', 'd/m/Y', 'd-m-Y']);
                $this->line('DATE:' . ($date ? $date->format('Y-m-d') : 'null'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-multi-slash', '--date' => '25/12/2024']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('DATE:2024-12-25', $output->fetch());
    }

    public function testMultiFormatDetectsDashFormat(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-multi-dash';
            static string $description = 'Multi format dash test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(format: ['Y-m-d', 'd/m/Y', 'd-m-Y']);
                $this->line('DATE:' . ($date ? $date->format('Y-m-d') : 'null'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-multi-dash', '--date' => '15-06-2024']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('DATE:2024-06-15', $output->fetch());
    }

    public function testMultiFormatDetectsCompactFormat(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-multi-compact';
            static string $description = 'Multi format compact test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(format: ['Y-m-d', 'd/m/Y', 'Ymd']);
                $this->line('DATE:' . ($date ? $date->format('Y-m-d') : 'null'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-multi-compact', '--date' => '20240615']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('DATE:2024-06-15', $output->fetch());
    }

    public function testMultiFormatThrowsWhenNoneMatch(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-multi-fail';
            static string $description = 'Multi format fail test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $this->dateOption(format: ['Y-m-d', 'd/m/Y']);
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-multi-fail', '--date' => 'not-a-date']);
        $output = new BufferedOutput();
        $status = $app->doRun($input, $output);

        $this->assertSame(1, $status);
        $result = $output->fetch();
        $this->assertStringContainsString('Invalid date value', $result);
        $this->assertStringContainsString('Y-m-d, d/m/Y', $result);
    }

    public function testSingleFormatStringStillWorks(): void
    {
        // Backward compatibility — single string format
        $output = $this->runCommand(['--date' => '2024-06-15']);
        $this->assertStringContainsString('DATE:2024-06-15', $output);
    }

    // --- Required ---

    public function testRequiredThrowsWhenNotProvided(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-required';
            static string $description = 'Required date test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(required: true);
                $this->line('DATE:' . $date->format('Y-m-d'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-required']);
        $output = new BufferedOutput();
        $status = $app->doRun($input, $output);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('--date option is required', $output->fetch());
    }

    public function testRequiredPassesWhenProvided(): void
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-required-ok';
            static string $description = 'Required date ok test';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(required: true);
                $this->line('DATE:' . $date->format('Y-m-d'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:date-required-ok', '--date' => '2024-06-15']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('DATE:2024-06-15', $output->fetch());
    }
}
