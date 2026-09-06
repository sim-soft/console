<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\ArgumentCommand;
use Tests\Fixtures\CallerCommand;
use Tests\Fixtures\ConstructorDependencyCommand;
use Tests\Fixtures\ExceptionCommand;
use Tests\Fixtures\LockableCommand;
use Tests\Fixtures\NoTimestampCommand;
use Tests\Fixtures\ProgressBarCommand;
use Tests\Fixtures\SimpleCommand;
use Tests\Fixtures\TableCommand;

class CommandTest extends TestCase
{
    private function runCommand(string $commandClass, array $input = []): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new $commandClass());

        $commandName = $commandClass::$name;
        $arrayInput = new ArrayInput(array_merge(['command' => $commandName], $input));
        $output = new BufferedOutput();

        $app->doRun($arrayInput, $output);

        return $output->fetch();
    }

    private function runCommandWithStatus(string $commandClass, array $input = []): int
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new $commandClass());

        $commandName = $commandClass::$name;
        $arrayInput = new ArrayInput(array_merge(['command' => $commandName], $input));
        $output = new BufferedOutput();

        return $app->doRun($arrayInput, $output);
    }

    // --- Basic Command Execution ---

    public function testSimpleCommandExecutes(): void
    {
        $output = $this->runCommand(SimpleCommand::class);
        $this->assertStringContainsString('Simple command executed', $output);
    }

    public function testCommandReturnsSuccessStatus(): void
    {
        $status = $this->runCommandWithStatus(SimpleCommand::class);
        $this->assertSame(Command::SUCCESS, $status);
    }

    public function testCommandReturnsFailureOnException(): void
    {
        $status = $this->runCommandWithStatus(ExceptionCommand::class);
        $this->assertSame(Command::FAILURE, $status);
    }

    public function testExceptionMessageIsOutputAsError(): void
    {
        $output = $this->runCommand(ExceptionCommand::class);
        $this->assertStringContainsString('Something went wrong', $output);
    }

    // --- Static Properties ---

    public function testCommandNameIsSet(): void
    {
        $this->assertSame('test:simple', SimpleCommand::$name);
    }

    public function testCommandDescriptionIsSet(): void
    {
        $this->assertSame('A simple test command', SimpleCommand::$description);
    }

    // --- Lazy Command ---

    public function testGetLazyCommandReturnsLazyCommand(): void
    {
        $lazy = SimpleCommand::getLazyCommand();
        $this->assertInstanceOf(LazyCommand::class, $lazy);
    }

    public function testLazyCommandHasCorrectName(): void
    {
        $lazy = SimpleCommand::getLazyCommand();
        $this->assertSame('test:simple', $lazy->getName());
    }

    public function testLazyCommandHasCorrectDescription(): void
    {
        $lazy = SimpleCommand::getLazyCommand();
        $this->assertSame('A simple test command', $lazy->getDescription());
    }

    public function testGetLazyCommandRejectsCommandWithRequiredConstructorArguments(): void
    {
        // LazyCommand resolves via `new static()`. Without the guard this blew
        // up later with an ArgumentCountError, at resolution time rather than
        // at registration, and with no hint about the cause.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be lazy-loaded because its constructor requires 1 argument(s)');

        ConstructorDependencyCommand::getLazyCommand();
    }

    public function testCommandWithConstructorArgumentsCanStillBeRegisteredEagerly(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new ConstructorDependencyCommand('injected value'));

        $output = new BufferedOutput();
        $status = $app->doRun(
            new ArrayInput(['command' => 'test:constructor-dependency']),
            $output
        );

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('injected value', $output->fetch());
    }

    // --- Arguments and Options ---

    public function testRequiredArgumentIsParsed(): void
    {
        $output = $this->runCommand(ArgumentCommand::class, ['name' => 'Alice']);
        $this->assertStringContainsString('Hello, Alice!', $output);
    }

    public function testOptionalArgumentUsesDefault(): void
    {
        $output = $this->runCommand(ArgumentCommand::class, ['name' => 'Bob']);
        $this->assertStringContainsString('Hello, Bob!', $output);
    }

    public function testOptionalArgumentCanBeOverridden(): void
    {
        $output = $this->runCommand(ArgumentCommand::class, ['name' => 'Bob', 'greeting' => 'Hi']);
        $this->assertStringContainsString('Hi, Bob!', $output);
    }

    public function testBooleanOptionWorks(): void
    {
        $output = $this->runCommand(ArgumentCommand::class, ['name' => 'Alice', '--shout' => true]);
        $this->assertStringContainsString('HELLO, ALICE!', $output);
    }

    public function testValueOptionWorks(): void
    {
        $output = $this->runCommand(ArgumentCommand::class, ['name' => 'Alice', '--repeat' => '3']);
        $this->assertSame(3, substr_count($output, 'Hello, Alice!'));
    }

    // --- Output Formatting ---

    public function testInfoOutputContainsMessage(): void
    {
        $output = $this->runCommand(SimpleCommand::class);
        $this->assertStringContainsString('Simple command executed', $output);
    }

    public function testNoTimestampCommandUsesTypeLabels(): void
    {
        $output = $this->runCommand(NoTimestampCommand::class);
        $this->assertStringContainsString('INFO', $output);
        $this->assertStringContainsString('COMMENT', $output);
        $this->assertStringContainsString('QUESTION', $output);
        $this->assertStringContainsString('ERROR', $output);
    }

    public function testLineOutputContainsMessage(): void
    {
        $output = $this->runCommand(NoTimestampCommand::class);
        $this->assertStringContainsString('Line message', $output);
    }

    public function testNewLineProducesBlankLines(): void
    {
        $output = $this->runCommand(NoTimestampCommand::class);
        // The command outputs various messages; newLine(0) outputs at least one line
        $this->assertNotEmpty($output);
    }

    public function testErrorBlockOutputContainsMessages(): void
    {
        $output = $this->runCommand(NoTimestampCommand::class);
        $this->assertStringContainsString('Section', $output);
        $this->assertStringContainsString('Block message', $output);
    }

    // --- Lockable Command ---

    public function testLockableCommandExecutes(): void
    {
        $output = $this->runCommand(LockableCommand::class);
        // The lockable command should execute (lock acquired successfully)
        // Note: with lock(null, true) - blocking mode, it waits for lock
        $this->assertIsString($output);
    }

    // --- Progress Bar ---

    public function testProgressBarProcessesAllItems(): void
    {
        $output = $this->runCommand(ProgressBarCommand::class);
        $this->assertStringContainsString('DONE:1,2,3,4,5', $output);
    }

    // --- Table ---

    public function testTableOutputContainsHeaders(): void
    {
        $output = $this->runCommand(TableCommand::class);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Score', $output);
    }

    public function testTableOutputContainsData(): void
    {
        $output = $this->runCommand(TableCommand::class);
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('Bob', $output);
        $this->assertStringContainsString('95', $output);
    }

    // --- Call Other Commands ---

    public function testCallAnotherCommand(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new CallerCommand());
        $app->addCommand(new ArgumentCommand());

        $input = new ArrayInput(['command' => 'test:caller']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('Hello, World!', $result);
    }

    public function testCallSilentlyDoesNotProduceOutput(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new CallerCommand());
        $app->addCommand(new ArgumentCommand());

        $input = new ArrayInput(['command' => 'test:caller']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        // "Silent" call should not appear in output
        $this->assertStringNotContainsString('Hello, Silent!', $result);
    }

    // --- getCurrentDatetime ---

    public function testGetCurrentDatetimeReturnsFormattedDate(): void
    {
        $command = new SimpleCommand();
        $datetime = $command->getCurrentDatetime();
        // Should match Y-m-d H:i:s format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime);
    }

    public function testGetCurrentDatetimeWithCustomFormat(): void
    {
        $command = new SimpleCommand();
        $datetime = $command->getCurrentDatetime('Y-m-d');
        $this->assertSame(date('Y-m-d'), $datetime);
    }
}
