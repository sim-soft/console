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

    // --- Time components ---

    /**
     * Run a command that prints the parsed date down to the second.
     *
     * The shared probe only prints Y-m-d, which is exactly the part that was
     * always correct — the fields the format does not name were the ones taken
     * from the current clock.
     *
     * @param string|string[] $format Format(s) passed to dateOption().
     * @param array<string, string> $input Extra input for the command.
     */
    private function runWithTime(string|array $format, array $input = []): string
    {
        $command = new class extends Command {
            use DateOption;

            static string $name = 'test:date-time';
            static string $description = 'Date time components test';

            /** @var string|string[] */
            public static string|array $format = 'Y-m-d';

            protected function init(): void
            {
                $this->addDateOption();
            }

            protected function handle(): void
            {
                $date = $this->dateOption(format: static::$format);
                $this->line('DATE:' . ($date ? $date->format('Y-m-d H:i:s') : 'null'));
            }
        };

        $command::$format = $format;

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $app->doRun(new ArrayInput(array_merge(['command' => 'test:date-time'], $input)), $output);

        return $output->fetch();
    }

    public function testDateOnlyValueParsesToMidnight(): void
    {
        // Without the '!' prefix the unnamed time fields came from the current
        // clock, so this passed only when the suite happened to run at 00:00.
        $output = $this->runWithTime('Y-m-d', ['--date' => '2024-06-15']);

        $this->assertStringContainsString('DATE:2024-06-15 00:00:00', $output);
    }

    public function testMonthOnlyFormatParsesToFirstOfMonthAtMidnight(): void
    {
        // 'Y-m' names neither the day nor the time, so the result used to carry
        // today's day of month as well.
        $output = $this->runWithTime('Y-m', ['--date' => '2024-06']);

        $this->assertStringContainsString('DATE:2024-06-01 00:00:00', $output);
    }

    public function testCompactDateParsesToMidnight(): void
    {
        $output = $this->runWithTime('Ymd', ['--date' => '20240615']);

        $this->assertStringContainsString('DATE:2024-06-15 00:00:00', $output);
    }

    public function testExplicitTimeInFormatIsStillHonoured(): void
    {
        // The '!' resets fields the format does not name; a format that does
        // name them must keep what the user typed.
        $output = $this->runWithTime('Y-m-d H:i:s', ['--date' => '2024-06-15 13:45:30']);

        $this->assertStringContainsString('DATE:2024-06-15 13:45:30', $output);
    }

    public function testDefaultTodayAndParsedValueAgreeOnTimeOfDay(): void
    {
        // The two paths of this method used to disagree with each other:
        // defaultToday returned midnight, the parsed value did not.
        $output = $this->runWithTime('Y-m-d', ['--date' => date('Y-m-d')]);

        $this->assertStringContainsString(
            'DATE:' . (new DateTimeImmutable('today'))->format('Y-m-d H:i:s'),
            $output
        );
    }

    // --- Rejected values ---

    public function testRolloverIsStillRejectedWithResetFields(): void
    {
        // The round-trip check compares against the original format, so the
        // '!' must not weaken it: PHP would turn this into 2025-02-14.
        $output = $this->runCommand(['--date' => '2024-13-45']);

        $this->assertStringContainsString('Invalid date value', $output);
        $this->assertStringNotContainsString('DATE:2025', $output);
    }

    public function testSurroundingWhitespaceIsAccepted(): void
    {
        $output = $this->runCommand(['--date' => '  2024-06-15  ']);

        $this->assertStringContainsString('DATE:2024-06-15', $output);
    }

    public function testNonStringValueIsRejectedWithAClearMessage(): void
    {
        // A hand-built ArrayInput can put an array here. It used to reach
        // createFromFormat() and raise a TypeError naming an internal function.
        $output = $this->runCommand(['--date' => ['2024-06-15', '2024-06-16']]);

        $this->assertStringContainsString('--date option must be a string', $output);
    }
}
