<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandOutputTest extends TestCase
{
    private function createAndRunCommand(callable $handleFn, array $input = []): string
    {
        // Create an anonymous command class
        $commandClass = new class extends Command {
            static string $name = 'test:output';
            static string $description = 'Output test command';
            protected bool $messageTimeStamp = false;

            public static ?\Closure $handleCallback = null;

            protected function handle(): void
            {
                if (static::$handleCallback) {
                    (static::$handleCallback)($this);
                }
            }
        };

        $commandClass::$handleCallback = \Closure::fromCallable($handleFn);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($commandClass);

        $arrayInput = new ArrayInput(array_merge(['command' => 'test:output'], $input));
        $output = new BufferedOutput();
        $app->doRun($arrayInput, $output);

        return $output->fetch();
    }

    // --- Table edge cases ---

    public function testTableWithClosure(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $cmd->table(
                ['Name', 'Upper'],
                [['alice'], ['bob']],
                function (array $row) {
                    return [$row[0], strtoupper($row[0])];
                }
            );
        });

        $this->assertStringContainsString('alice', $output);
        $this->assertStringContainsString('ALICE', $output);
        $this->assertStringContainsString('bob', $output);
        $this->assertStringContainsString('BOB', $output);
    }

    public function testTableWithNonArrayRowThrowsException(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            try {
                $cmd->table(['Col'], ['not-an-array']);
            } catch (Exception $e) {
                $cmd->line('EXCEPTION:' . $e->getMessage());
            }
        });

        $this->assertStringContainsString('EXCEPTION:Each row should be an array', $output);
    }

    public function testTableWithEmptyData(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $cmd->table(['Name', 'Score'], []);
        });

        // Should render headers at minimum
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Score', $output);
    }

    // --- newLine edge cases ---

    public function testNewLineWithZeroRepeat(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $cmd->line('BEFORE');
            $cmd->newLine(0);
            $cmd->line('AFTER');
        });

        // newLine(0) should output at least one blank line (do-while)
        $this->assertStringContainsString('BEFORE', $output);
        $this->assertStringContainsString('AFTER', $output);
    }

    public function testNewLineWithMultipleRepeats(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $cmd->newLine(3);
            $cmd->line('END');
        });

        $this->assertStringContainsString('END', $output);
    }

    // --- Progress bar with custom maxSteps ---

    public function testProgressBarWithMaxSteps(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $items = range(1, 3);
            $results = [];
            $cmd->withProgressBar($items, function ($item) use (&$results) {
                $results[] = $item;
            }, 10);
            $cmd->newLine();
            $cmd->line('RESULTS:' . implode(',', $results));
        });

        $this->assertStringContainsString('RESULTS:1,2,3', $output);
    }

    public function testProgressBarTraversesAGenerator(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $gen = (function () {
                yield 1;
                yield 2;
                yield 3;
            })();

            $results = [];
            $cmd->withProgressBar($gen, function ($item) use (&$results) {
                $results[] = $item;
            });
            $cmd->newLine();
            $cmd->line('RESULTS:' . implode(',', $results));
        });

        $this->assertStringContainsString('RESULTS:1,2,3', $output);
    }

    public function testProgressBarRejectsCountableThatIsNotIterable(): void
    {
        // The signature accepts Countable, but a Countable that is not also
        // iterable cannot be walked. This used to render a full progress bar
        // while invoking the callback zero times — a complete run over nothing.
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $bag = new class implements \Countable {
                public function count(): int
                {
                    return 3;
                }
            };

            $invoked = 0;
            try {
                $cmd->withProgressBar($bag, function () use (&$invoked) {
                    $invoked++;
                });
                $cmd->line("NO ERROR, callback invoked $invoked times");
            } catch (\InvalidArgumentException $e) {
                $cmd->line('REJECTED: ' . $e->getMessage());
            }
        });

        $this->assertStringContainsString('REJECTED:', $output);
        $this->assertStringContainsString('is Countable but not iterable', $output);
    }

    // --- createProgressBar ---

    public function testCreateProgressBar(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $bar = $cmd->createProgressBar(5);
            $bar->start();
            for ($i = 0; $i < 5; $i++) {
                $bar->advance();
            }
            $bar->finish();
            $cmd->newLine();
            $cmd->line('BAR_DONE');
        });

        $this->assertStringContainsString('BAR_DONE', $output);
    }

    // --- errorBlock ---

    public function testErrorBlockWithLabelOff(): void
    {
        $output = $this->createAndRunCommand(function (Command $cmd) {
            $cmd->errorBlock('Header', 'Message', true);
        });

        $this->assertStringContainsString('Header', $output);
        $this->assertStringContainsString('Message', $output);
    }

    // --- arguments() and options() ---

    public function testArgumentsReturnsAllArguments(): void
    {
        $commandClass = new class extends Command {
            static string $name = 'test:all-args';
            static string $description = 'All args test';

            protected function init(): void
            {
                $this->addArgument('first');
                $this->addArgument('second');
            }

            protected function handle(): void
            {
                $args = $this->arguments();
                $this->line('ARGS:' . implode(',', array_filter($args)));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($commandClass);

        $input = new ArrayInput(['command' => 'test:all-args', 'first' => 'a', 'second' => 'b']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('ARGS:', $result);
        $this->assertStringContainsString('a', $result);
        $this->assertStringContainsString('b', $result);
    }

    public function testArgumentWithDefault(): void
    {
        $commandClass = new class extends Command {
            static string $name = 'test:arg-default';
            static string $description = 'Arg default test';

            protected function init(): void
            {
                $this->addArgument('name', \Symfony\Component\Console\Input\InputArgument::OPTIONAL);
            }

            protected function handle(): void
            {
                $name = $this->argument('name', 'DefaultName');
                $this->line("NAME:$name");
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($commandClass);

        $input = new ArrayInput(['command' => 'test:arg-default']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('NAME:DefaultName', $result);
    }

    public function testOptionsReturnsAllOptions(): void
    {
        $commandClass = new class extends Command {
            static string $name = 'test:all-opts';
            static string $description = 'All opts test';

            protected function init(): void
            {
                $this->addOption('color', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, '', 'red');
            }

            protected function handle(): void
            {
                $opts = $this->options();
                $this->line('COLOR:' . ($opts['color'] ?? 'none'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($commandClass);

        $input = new ArrayInput(['command' => 'test:all-opts']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('COLOR:red', $result);
    }

    public function testOptionWithDefault(): void
    {
        $commandClass = new class extends Command {
            static string $name = 'test:opt-default';
            static string $description = 'Opt default test';

            protected function init(): void
            {
                $this->addOption('level', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL);
            }

            protected function handle(): void
            {
                $level = $this->option('level', 'high');
                $this->line("LEVEL:$level");
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($commandClass);

        $input = new ArrayInput(['command' => 'test:opt-default']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('LEVEL:high', $result);
    }
}
