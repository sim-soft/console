<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Commands\ScheduleRunCommand;
use Simsoft\Console\Schedule;
use Simsoft\Console\Scheduler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests for the mixed-value paths PHPStan level 9 surfaced.
 *
 * QuestionHelper::ask() returns mixed and the scheduler's arguments are
 * mixed-valued, so neither was checked. Each case below is a real failure that
 * reached a user before these guards: a TypeError naming the wrong frame, or a
 * background process launched with an argument nobody wrote.
 */
class PromptContractTest extends TestCase
{
    /**
     * Run a command's handle() with non-interactive input.
     *
     * @return array{status: int, output: string}
     */
    private function runCommand(Command $command): array
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => $command->getName()]);
        $input->setInteractive(false);
        $output = new BufferedOutput();

        return ['status' => $app->doRun($input, $output), 'output' => $output->fetch()];
    }

    /** Invoke ScheduleRunCommand's private background command builder. */
    private function buildBackgroundCommand(Schedule $schedule): string
    {
        $method = new ReflectionMethod(ScheduleRunCommand::class, 'buildBackgroundCommand');

        return $method->invoke(new ScheduleRunCommand(new Scheduler()), $schedule);
    }

    // --- choice() with no answer available ---

    public function testChoiceWithoutDefaultOnNonInteractiveInputExplainsItself(): void
    {
        // Previously: "Return value must be of type array|string, null returned",
        // which named choice() but not the reason it had nothing to return.
        $result = $this->runCommand(new class extends Command {
            public static string $name = 'test:choice-no-default';
            protected bool $messageTimeStamp = false;
            protected function handle(): void
            {
                $this->choice('Pick one', ['alpha', 'beta']);
            }
        });

        $this->assertSame(Command::FAILURE, $result['status']);
        $this->assertStringContainsString('input is not interactive', $result['output']);
    }

    public function testChoiceErrorNamesTheQuestionAndTheFix(): void
    {
        $result = $this->runCommand(new class extends Command {
            public static string $name = 'test:choice-names-question';
            protected bool $messageTimeStamp = false;
            protected function handle(): void
            {
                $this->choice('Which environment', ['dev', 'prod']);
            }
        });

        $this->assertStringContainsString('Which environment', $result['output']);
        $this->assertStringContainsString('$defaultIndex', $result['output']);
    }

    public function testChoiceWithADefaultStillAnswersOnNonInteractiveInput(): void
    {
        $result = $this->runCommand(new class extends Command {
            public static string $name = 'test:choice-with-default';
            protected bool $messageTimeStamp = false;
            protected function handle(): void
            {
                $this->line('picked:' . $this->choice('Pick one', ['alpha', 'beta'], 1));
            }
        });

        $this->assertSame(Command::SUCCESS, $result['status']);
        $this->assertStringContainsString('picked:beta', $result['output']);
    }

    // --- scalarAnswer() guards the prompt contract ---

    public function testNonScalarAnswerIsReportedAgainstTheCallingMethod(): void
    {
        $command = new class extends Command {
            public static string $name = 'test:non-scalar-answer';
            protected bool $messageTimeStamp = false;
            protected function handle(): void
            {
            }

            public function probe(): mixed
            {
                return $this->scalarAnswer(new \stdClass(), 'ask');
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ask() answered stdClass');
        $command->probe();
    }

    public function testScalarAnswersPassThroughUnchanged(): void
    {
        $command = new class extends Command {
            public static string $name = 'test:scalar-answer';
            protected bool $messageTimeStamp = false;
            protected function handle(): void
            {
            }

            public function probe(mixed $answer): mixed
            {
                return $this->scalarAnswer($answer, 'ask');
            }
        };

        $this->assertSame('text', $command->probe('text'));
        $this->assertSame(0, $command->probe(0));
        $this->assertFalse($command->probe(false));
        $this->assertNull($command->probe(null));
    }

    // --- background command arguments ---

    public function testArrayValuedOptionIsRepeatedRatherThanStringified(): void
    {
        // Previously produced --tag=Array behind an "Array to string conversion"
        // warning, so the background task ran with an argument nobody wrote.
        $command = $this->buildBackgroundCommand(new Schedule('app:sync', ['--tag' => ['a', 'b']]));

        $this->assertStringContainsString('--tag=a', $command);
        $this->assertStringContainsString('--tag=b', $command);
        $this->assertStringNotContainsString('Array', $command);
    }

    public function testArrayValuedPositionalArgumentIsRepeated(): void
    {
        $command = $this->buildBackgroundCommand(new Schedule('app:sync', ['files' => ['one', 'two']]));

        $this->assertStringContainsString('one', $command);
        $this->assertStringContainsString('two', $command);
        $this->assertStringNotContainsString('Array', $command);
    }

    public function testBooleanOptionIsPassedAsAWordNotAnEmptyString(): void
    {
        // (string)false is "", which is indistinguishable from an omitted value.
        $command = $this->buildBackgroundCommand(new Schedule('app:sync', ['--dry-run' => false]));

        $this->assertStringContainsString('--dry-run=false', $command);
    }

    public function testStringableArgumentIsRendered(): void
    {
        $value = new class implements \Stringable {
            public function __toString(): string
            {
                return '2026-09-06';
            }
        };

        $command = $this->buildBackgroundCommand(new Schedule('app:sync', ['--date' => $value]));

        $this->assertStringContainsString('--date=2026-09-06', $command);
    }

    public function testArgumentWithNoStringFormIsRejectedByName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scheduled argument "--payload" is stdClass');

        $this->buildBackgroundCommand(new Schedule('app:sync', ['--payload' => new \stdClass()]));
    }

    // --- entry script resolution ---

    public function testScriptPathFallsBackWhenArgvIsNotAList(): void
    {
        // A string in $_SERVER['argv'] indexed to its first character, so the
        // task was launched against a one-letter path that does not exist. The
        // `?? 'console'` fallback only covered argv being absent.
        $original = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = 'not-an-array';

        try {
            $command = $this->buildBackgroundCommand(new Schedule('app:sync', []));
            $this->assertStringContainsString('console', $command);
            $this->assertStringNotContainsString('"n"', $command);
        } finally {
            $_SERVER['argv'] = $original;
        }
    }

    public function testScriptPathUsesArgvWhenItIsAList(): void
    {
        $original = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['bin/my-console', 'schedule:run'];

        try {
            $this->assertStringContainsString(
                'bin/my-console',
                $this->buildBackgroundCommand(new Schedule('app:sync', []))
            );
        } finally {
            $_SERVER['argv'] = $original;
        }
    }
}
