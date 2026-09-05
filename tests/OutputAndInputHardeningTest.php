<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\RetryableTask;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Regression tests for output and input handling defects:
 *  - retry() throwing a raw Error when maxAttempts <= 0
 *  - callSilently() leaving the shared output stuck at QUIET
 *  - errorBlock() emitting an unbalanced closing bracket
 *  - newLine() writing timestamped "blank" lines
 */
class OutputAndInputHardeningTest extends TestCase
{
    private function runCommand(Command $command, array $input = []): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $app->doRun(new ArrayInput(array_merge(['command' => $command->getName()], $input)), $output);

        return $output->fetch();
    }

    // --- retry() must not throw a raw Error on a non-positive attempt count ---

    public function testRetryRejectsZeroMaxAttempts(): void
    {
        $command = new class extends Command {
            use RetryableTask;

            static string $name = 'test:retry-zero';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                try {
                    $this->retry(fn() => 'never runs', maxAttempts: 0);
                } catch (InvalidArgumentException $e) {
                    $this->line('caught:' . $e->getMessage());
                    return;
                }

                $this->line('caught:nothing');
            }
        };

        $output = $this->runCommand($command);

        // Previously: "Error: Can only throw objects" from `throw null`.
        $this->assertStringContainsString('caught:Maximum number of attempts must be at least 1', $output);
        $this->assertStringNotContainsString('Can only throw objects', $output);
    }

    public function testRetryRejectsNegativeMaxAttempts(): void
    {
        $command = new class extends Command {
            use RetryableTask;

            static string $name = 'test:retry-negative';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $this->retry(fn() => 'never runs', maxAttempts: -5);
            }
        };

        $output = $this->runCommand($command);

        $this->assertStringContainsString('must be at least 1', $output);
        $this->assertStringNotContainsString('Can only throw objects', $output);
    }

    public function testRetryStillPropagatesTheLastFailure(): void
    {
        $command = new class extends Command {
            use RetryableTask;

            static string $name = 'test:retry-exhausted';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                try {
                    $this->retry(
                        fn() => throw new RuntimeException('always fails'),
                        maxAttempts: 2,
                        delayMs: 0,
                    );
                } catch (RuntimeException $e) {
                    $this->line('propagated:' . $e->getMessage());
                }
            }
        };

        $output = $this->runCommand($command);

        $this->assertStringContainsString('propagated:always fails', $output);
    }

    public function testRetrySucceedsOnASubsequentAttempt(): void
    {
        $command = new class extends Command {
            use RetryableTask;

            static string $name = 'test:retry-recovers';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $calls = 0;
                $result = $this->retry(
                    function () use (&$calls) {
                        if (++$calls < 3) {
                            throw new RuntimeException('not yet');
                        }
                        return 'recovered';
                    },
                    maxAttempts: 3,
                    delayMs: 0,
                );

                $this->line("result:$result attempts:$calls");
            }
        };

        $output = $this->runCommand($command);

        $this->assertStringContainsString('result:recovered attempts:3', $output);
    }

    // --- callSilently() must restore the previous verbosity ---

    public function testCallSilentlyRestoresVerbosityForTheCaller(): void
    {
        $inner = new class extends Command {
            static string $name = 'test:inner-quiet';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $this->line('INNER_OUTPUT');
            }
        };

        $caller = new class extends Command {
            static string $name = 'test:caller-quiet';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $this->line('BEFORE_CALL');
                $this->callSilently('test:inner-quiet');
                $this->line('AFTER_CALL');
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($inner);
        $app->addCommand($caller);

        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $app->doRun(new ArrayInput(['command' => 'test:caller-quiet']), $output);
        $result = $output->fetch();

        $this->assertStringContainsString('BEFORE_CALL', $result);
        // The inner command stays silent...
        $this->assertStringNotContainsString('INNER_OUTPUT', $result);
        // ...but the caller must keep its voice afterwards.
        $this->assertStringContainsString('AFTER_CALL', $result);
        $this->assertSame(OutputInterface::VERBOSITY_NORMAL, $output->getVerbosity());
    }

    public function testCallSilentlyRestoresVerbosityWhenTheInnerCommandFails(): void
    {
        $inner = new class extends Command {
            static string $name = 'test:inner-explodes';

            protected function handle(): void
            {
                throw new RuntimeException('inner blew up');
            }
        };

        $caller = new class extends Command {
            static string $name = 'test:caller-after-failure';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $this->callSilently('test:inner-explodes');
                $this->line('STILL_AUDIBLE');
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($inner);
        $app->addCommand($caller);

        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $app->doRun(new ArrayInput(['command' => 'test:caller-after-failure']), $output);

        $this->assertStringContainsString('STILL_AUDIBLE', $output->fetch());
    }

    public function testCallSilentlyPreservesAnElevatedVerbosity(): void
    {
        $inner = new class extends Command {
            static string $name = 'test:inner-verbose';

            protected function handle(): void
            {
            }
        };

        $caller = new class extends Command {
            static string $name = 'test:caller-verbose';

            protected function handle(): void
            {
                $this->callSilently('test:inner-verbose');
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($inner);
        $app->addCommand($caller);

        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $app->doRun(new ArrayInput(['command' => 'test:caller-verbose']), $output);

        $this->assertSame(OutputInterface::VERBOSITY_VERY_VERBOSE, $output->getVerbosity());
    }

    // --- errorBlock() bracket balance ---

    public function testErrorBlockLabelIsBracketBalanced(): void
    {
        $command = new class extends Command {
            static string $name = 'test:error-block';

            protected function handle(): void
            {
                $this->errorBlock('SECTION', 'MESSAGE');
            }
        };

        $output = $this->runCommand($command);

        $this->assertStringContainsString('SECTION', $output);
        $this->assertSame(
            substr_count($output, '['),
            substr_count($output, ']'),
            'errorBlock() emitted an unbalanced bracket.'
        );
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] SECTION/', $output);
    }

    public function testErrorBlockWithLabelOffHasNoTimestamp(): void
    {
        $command = new class extends Command {
            static string $name = 'test:error-block-off';

            protected function handle(): void
            {
                $this->errorBlock('SECTION', 'MESSAGE', true);
            }
        };

        $output = $this->runCommand($command);

        $this->assertStringContainsString('SECTION', $output);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $output);
        $this->assertStringNotContainsString(']', $output);
    }

    // --- newLine() must emit genuinely blank lines ---

    public function testNewLineEmitsBlankLinesWithoutTimestamps(): void
    {
        $command = new class extends Command {
            static string $name = 'test:newline-blank';

            protected function handle(): void
            {
                $this->line('BEFORE');
                $this->newLine(2);
                $this->line('AFTER');
            }
        };

        $output = $this->runCommand($command);
        $lines = preg_split('/\R/', $output);

        $before = array_search('[' . date('Y-m-d') . '', array_map(
            static fn($line) => substr($line, 0, 11),
            $lines
        ), true);
        $this->assertNotFalse($before, 'Expected at least one timestamped line.');

        $blank = array_values(array_filter($lines, static fn($line) => $line === ''));
        $this->assertGreaterThanOrEqual(2, count($blank), 'Expected two genuinely blank lines.');

        // No line may be nothing but a timestamp label.
        foreach ($lines as $line) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s*$/',
                $line,
                'newLine() emitted a timestamped blank line.'
            );
        }
    }

    public function testNewLineDefaultsToASingleBlankLine(): void
    {
        $command = new class extends Command {
            static string $name = 'test:newline-default';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $this->line('A');
                $this->newLine();
                $this->line('B');
            }
        };

        $output = $this->runCommand($command);

        $this->assertSame("A\n\nB\n", str_replace("\r\n", "\n", $output));
    }

    public function testNewLineWithZeroStillEmitsOneBlankLine(): void
    {
        $command = new class extends Command {
            static string $name = 'test:newline-zero';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $this->line('A');
                $this->newLine(0);
                $this->line('B');
            }
        };

        $output = $this->runCommand($command);

        $this->assertSame("A\n\nB\n", str_replace("\r\n", "\n", $output));
    }
}
