<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\ClosureCommand;
use Simsoft\Console\CommandBuilder;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

class ClosureCommandTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset Application static state
        $reflection = new \ReflectionClass(Application::class);

        $closureCommands = $reflection->getProperty('closureCommands');
        $closureCommands->setValue(null, []);

        $commands = $reflection->getProperty('commands');
        $commands->setValue(null, []);

        $app = $reflection->getProperty('app');
        $app->setValue(null, null);
    }

    private function buildAndRun(CommandBuilder $builder, array $input = []): string
    {
        $command = $builder->build();

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $arrayInput = new ArrayInput(array_merge(['command' => $command->getName()], $input));
        $output = new BufferedOutput();
        $app->doRun($arrayInput, $output);

        return $output->fetch();
    }

    // --- Constructor ---

    public function testConstructorSetsName(): void
    {
        $command = new ClosureCommand('test:closure-name');
        $this->assertSame('test:closure-name', $command->getName());
    }

    public function testConstructorWithNullInputCallback(): void
    {
        $command = new ClosureCommand('test:no-input', null);
        // Should not throw - inputClosure is null
        $this->assertSame('test:no-input', $command->getName());
    }

    public function testConstructorWithInputCallback(): void
    {
        $callback = function () {
            $this->addArgument('test_arg');
        };

        $command = new ClosureCommand('test:with-input', $callback);
        $this->assertSame('test:with-input', $command->getName());
    }

    // --- setHandler ---

    public function testSetHandlerReturnsSelf(): void
    {
        $command = new ClosureCommand('test:handler');
        $result = $command->setHandler(function () {
        });
        $this->assertSame($command, $result);
    }

    // --- handle() with callback ---

    public function testHandleExecutesCallback(): void
    {
        $builder = new CommandBuilder('test:exec', function () {
            $this->line('EXECUTED');
        });

        $output = $this->buildAndRun($builder);
        $this->assertStringContainsString('EXECUTED', $output);
    }

    public function testHandleWithNullCallback(): void
    {
        $command = new ClosureCommand('test:null-cb');
        // No handler set — should execute without error

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:null-cb']);
        $output = new BufferedOutput();
        $status = $app->doRun($input, $output);

        $this->assertSame(0, $status);
    }

    // --- init() with inputCallback ---

    public function testInitExecutesInputCallback(): void
    {
        $builder = new CommandBuilder('test:init-input', function () {
            $name = $this->argument('myarg');
            $this->line("ARG:$name");
        });
        $builder->input(function () {
            $this->addArgument('myarg', InputArgument::REQUIRED);
        });

        $output = $this->buildAndRun($builder, ['myarg' => 'hello']);
        $this->assertStringContainsString('ARG:hello', $output);
    }

    // --- Closure has access to command methods ---

    public function testClosureCanAccessInfoMethod(): void
    {
        $builder = new CommandBuilder('test:info-access', function () {
            $this->info('Info from closure');
        });

        $output = $this->buildAndRun($builder);
        $this->assertStringContainsString('Info from closure', $output);
    }

    public function testClosureCanAccessErrorMethod(): void
    {
        $builder = new CommandBuilder('test:error-access', function () {
            $this->error('Error from closure');
        });

        $output = $this->buildAndRun($builder);
        $this->assertStringContainsString('Error from closure', $output);
    }

    public function testClosureCanAccessCommentMethod(): void
    {
        $builder = new CommandBuilder('test:comment-access', function () {
            $this->comment('Comment from closure');
        });

        $output = $this->buildAndRun($builder);
        $this->assertStringContainsString('Comment from closure', $output);
    }

    public function testClosureCanAccessOptions(): void
    {
        $builder = new CommandBuilder('test:opt-access', function () {
            $val = $this->option('myopt');
            $this->line("OPT:$val");
        });
        $builder->input(function () {
            $this->addOption('myopt', null, InputOption::VALUE_REQUIRED);
        });

        $output = $this->buildAndRun($builder, ['--myopt' => 'value123']);
        $this->assertStringContainsString('OPT:value123', $output);
    }

    public function testClosureCanAccessArguments(): void
    {
        $builder = new CommandBuilder('test:arg-access', function () {
            $args = $this->arguments();
            $this->line('ARGS:' . implode(',', array_values($args)));
        });
        $builder->input(function () {
            $this->addArgument('first', InputArgument::REQUIRED);
            $this->addArgument('second', InputArgument::OPTIONAL, '', 'default');
        });

        $output = $this->buildAndRun($builder, ['first' => 'one']);
        $this->assertStringContainsString('one', $output);
    }

    public function testClosureCanCallLine(): void
    {
        $builder = new CommandBuilder('test:line-access', function () {
            $this->line('Plain line output');
        });

        $output = $this->buildAndRun($builder);
        $this->assertStringContainsString('Plain line output', $output);
    }

    // --- Multiple closure commands don't interfere ---

    public function testMultipleClosureCommandsAreIndependent(): void
    {
        $builder1 = new CommandBuilder('test:cmd-one', function () {
            $this->line('OUTPUT_ONE');
        });
        $builder2 = new CommandBuilder('test:cmd-two', function () {
            $this->line('OUTPUT_TWO');
        });

        $cmd1 = $builder1->build();
        $cmd2 = $builder2->build();

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($cmd1);
        $app->addCommand($cmd2);

        // Run first command
        $input1 = new ArrayInput(['command' => 'test:cmd-one']);
        $output1 = new BufferedOutput();
        $app->doRun($input1, $output1);

        // Run second command
        $input2 = new ArrayInput(['command' => 'test:cmd-two']);
        $output2 = new BufferedOutput();
        $app->doRun($input2, $output2);

        $this->assertStringContainsString('OUTPUT_ONE', $output1->fetch());
        $this->assertStringContainsString('OUTPUT_TWO', $output2->fetch());
    }
}
