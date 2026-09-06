<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Scheduler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests that a command invoked from code cannot block on a prompt.
 *
 * Symfony asks for confirmation when an unknown command name has exactly one
 * close match ('Do you want to run "deploy:run" instead?'), and a command may
 * prompt in its own right. A fresh ArrayInput is interactive, so every
 * programmatic entry point could reach one of those prompts and block on stdin
 * forever — in a container, a supervised worker, or CI, where the stream stays
 * open and nothing answers. The process still looks healthy to monitoring,
 * which is what makes it worse than a crash.
 *
 * Where stdin was already closed it happened to return, which is how this
 * survived: it failed only in the environments least likely to be watched.
 */
class NonInteractiveDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        Application::flushGlobal();
    }

    protected function tearDown(): void
    {
        Application::flushGlobal();
    }

    /** Report whether the command saw interactive input when it ran. */
    private function interactivityProbe(string $name): Command
    {
        return new class($name) extends Command {
            public ?bool $wasInteractive = null;

            public function __construct(string $name)
            {
                static::$name = $name;
                parent::__construct();
            }

            protected function handle(): void
            {
                $this->wasInteractive = $this->input->isInteractive();
            }
        };
    }

    // --- Application::programmaticInput() ---

    public function testProgrammaticInputIsNotInteractive(): void
    {
        $input = Application::programmaticInput('deploy:run', ['--force' => true]);

        $this->assertFalse(
            $input->isInteractive(),
            'A command invoked from code has nobody at the keyboard to answer a prompt.'
        );
    }

    public function testProgrammaticInputCarriesTheCommandAndArguments(): void
    {
        $input = Application::programmaticInput('deploy:run', ['--force' => true]);

        $this->assertSame('deploy:run', $input->getFirstArgument());
        $this->assertTrue($input->getParameterOption('--force'));
    }

    // --- Application::call() ---

    public function testStaticCallRunsTheCommandNonInteractively(): void
    {
        $probe = $this->interactivityProbe('probe:static-call');

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($probe);
        $app->shareGlobally();

        Application::call('probe:static-call');

        $this->assertFalse($probe->wasInteractive);
    }

    public function testStaticCallWithAnUnknownNameReturnsInsteadOfPrompting(): void
    {
        // The name below is one character from the registered one, which is what
        // triggers Symfony's single-alternative confirmation prompt. With
        // interactive input this blocked on stdin rather than returning.
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($this->interactivityProbe('deploy:run'));
        $app->shareGlobally();

        $this->assertSame(Command::FAILURE, Application::call('deploy:runn'));
    }

    public function testStaticCallDoesNotRunTheNearlyMatchingCommand(): void
    {
        // Declining the prompt is the safe default: a typo must not silently
        // run a different command.
        $probe = $this->interactivityProbe('deploy:run');

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($probe);
        $app->shareGlobally();

        Application::call('deploy:runn');

        $this->assertNull($probe->wasInteractive, 'deploy:run must not have run.');
    }

    // --- Command::call() inherits, rather than forcing ---

    public function testSubCommandInheritsNonInteractiveInput(): void
    {
        $probe = $this->interactivityProbe('probe:sub');
        $caller = $this->callerInvoking('probe:sub', 'probe:caller', silently: false);

        $this->runCommands([$caller, $probe], 'probe:caller', interactive: false);

        $this->assertFalse($probe->wasInteractive, '--no-interaction must not stop at the first command.');
    }

    public function testSubCommandInheritsInteractiveInput(): void
    {
        // The inverse case: at a terminal a sub-command may still prompt, so
        // interactivity is inherited rather than forced off.
        $probe = $this->interactivityProbe('probe:sub-interactive');
        $caller = $this->callerInvoking('probe:sub-interactive', 'probe:caller-interactive', silently: false);

        $this->runCommands([$caller, $probe], 'probe:caller-interactive', interactive: true);

        $this->assertTrue($probe->wasInteractive);
    }

    public function testCallSilentlyInheritsNonInteractiveInput(): void
    {
        $probe = $this->interactivityProbe('probe:sub-silent');
        $caller = $this->callerInvoking('probe:sub-silent', 'probe:caller-silent', silently: true);

        $this->runCommands([$caller, $probe], 'probe:caller-silent', interactive: false);

        $this->assertFalse($probe->wasInteractive);
    }

    // --- scheduled tasks ---

    public function testScheduledTaskRunsNonInteractively(): void
    {
        $probe = $this->interactivityProbe('probe:scheduled');

        $this->runScheduled($probe, fn(Scheduler $s) => $s->command('probe:scheduled')->everyMinute());

        $this->assertFalse($probe->wasInteractive);
    }

    public function testScheduledTaskWithOutputCaptureRunsNonInteractively(): void
    {
        $probe = $this->interactivityProbe('probe:scheduled-output');
        $path = sys_get_temp_dir() . '/schedule-interactivity-' . uniqid() . '.log';

        try {
            $this->runScheduled(
                $probe,
                fn(Scheduler $s) => $s->command('probe:scheduled-output')->everyMinute()->sendOutputTo($path)
            );

            $this->assertFalse($probe->wasInteractive);
        } finally {
            @unlink($path);
        }
    }

    public function testScheduledTaskIsNonInteractiveEvenWhenScheduleRunIsNot(): void
    {
        // An operator running schedule:run by hand must not give every task the
        // ability to prompt: the same task would then behave differently under
        // cron than it did in testing.
        $probe = $this->interactivityProbe('probe:scheduled-by-hand');

        $this->runScheduled(
            $probe,
            fn(Scheduler $s) => $s->command('probe:scheduled-by-hand')->everyMinute(),
            interactive: true
        );

        $this->assertFalse($probe->wasInteractive);
    }

    // --- helpers ---

    /** Build a command that dispatches $target when it runs. */
    private function callerInvoking(string $target, string $name, bool $silently): Command
    {
        return new class($target, $name, $silently) extends Command {
            public function __construct(
                private readonly string $target,
                string $name,
                private readonly bool $silently,
            ) {
                static::$name = $name;
                parent::__construct();
            }

            protected function handle(): void
            {
                $this->silently
                    ? $this->callSilently($this->target)
                    : $this->call($this->target);
            }
        };
    }

    /**
     * Run $entry against an application holding $commands.
     *
     * @param array<int, Command> $commands
     */
    private function runCommands(array $commands, string $entry, bool $interactive): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);

        foreach ($commands as $command) {
            $app->addCommand($command);
        }

        $input = new ArrayInput(['command' => $entry]);
        $input->setInteractive($interactive);

        $app->doRun($input, new BufferedOutput());
    }

    /** Run schedule:run over a scheduler configured by $configure. */
    private function runScheduled(Command $task, callable $configure, bool $interactive = false): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($task);
        $app->withScheduler($configure(...));

        $input = new ArrayInput(['command' => 'schedule:run']);
        $input->setInteractive($interactive);

        $app->doRun($input, new BufferedOutput());
    }
}
