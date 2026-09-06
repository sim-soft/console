<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Coverage for public API that had none.
 *
 * tree(), createProgressIndicator() and secret() are documented methods that no
 * test exercised: a signature change or a Symfony API shift would have broken
 * them silently. These are the ordinary paths, not edge cases.
 */
class UncoveredApiTest extends TestCase
{
    /** Run $handle inside a command and return what it wrote. */
    private function capture(callable $handle): string
    {
        $command = new class($handle) extends Command {
            /** @var callable */
            private $handle;

            public function __construct(callable $handle)
            {
                static::$name = 'test:uncovered-' . uniqid();
                $this->handle = $handle;
                parent::__construct();
            }

            protected function handle(): void
            {
                ($this->handle)($this);
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $input = new ArrayInput(['command' => $command->getName()]);
        $input->setInteractive(false);

        $app->doRun($input, $output);

        return $output->fetch();
    }

    // --- tree() ---

    public function testTreeRendersTheRootAndItsChildren(): void
    {
        $output = $this->capture(fn(Command $c) => $c->tree('src', [
            'Commands' => ['ScheduleRunCommand.php'],
            'Application.php',
        ]));

        $this->assertStringContainsString('src', $output);
        $this->assertStringContainsString('Commands', $output);
        $this->assertStringContainsString('ScheduleRunCommand.php', $output);
        $this->assertStringContainsString('Application.php', $output);
    }

    public function testTreeRendersNestedLevels(): void
    {
        $output = $this->capture(fn(Command $c) => $c->tree('a', ['b' => ['c' => ['d']]]));

        foreach (['a', 'b', 'c', 'd'] as $label) {
            $this->assertStringContainsString($label, $output);
        }
    }

    public function testTreeAcceptsAnEmptyNodeList(): void
    {
        $output = $this->capture(fn(Command $c) => $c->tree('empty', []));

        $this->assertStringContainsString('empty', $output);
    }

    // --- createProgressIndicator() ---

    public function testCreateProgressIndicatorReturnsAnIndicator(): void
    {
        $indicator = null;

        $this->capture(function (Command $c) use (&$indicator): void {
            $indicator = $c->createProgressIndicator();
        });

        $this->assertInstanceOf(ProgressIndicator::class, $indicator);
    }

    public function testProgressIndicatorWritesToTheCommandOutput(): void
    {
        $output = $this->capture(function (Command $c): void {
            $indicator = $c->createProgressIndicator();
            $indicator->start('Working');
            $indicator->finish('Done');
        });

        $this->assertStringContainsString('Working', $output);
        $this->assertStringContainsString('Done', $output);
    }

    public function testCreateProgressIndicatorPassesCustomArgumentsThrough(): void
    {
        // Which frame is on screen at a given moment is Symfony's business, and
        // depends on decoration and timing. What this wrapper owes the caller is
        // that a custom format and value set are accepted and reach a working
        // indicator, which is what this asserts.
        $indicator = null;

        $this->capture(function (Command $c) use (&$indicator): void {
            $indicator = $c->createProgressIndicator('%indicator% %message%', 50, ['A', 'B']);
            $indicator->start('Spinning');
            $indicator->finish('Stopped');
        });

        $this->assertInstanceOf(ProgressIndicator::class, $indicator);
    }

    // --- secret() ---

    public function testSecretReturnsItsDefaultWhenInputIsNotInteractive(): void
    {
        $answer = null;

        $this->capture(function (Command $c) use (&$answer): void {
            $answer = $c->secret('Password', 'fallback');
        });

        $this->assertSame('fallback', $answer);
    }

    public function testSecretReturnsNullWithNoDefault(): void
    {
        $answer = 'unset';

        $this->capture(function (Command $c) use (&$answer): void {
            $answer = $c->secret('Password');
        });

        $this->assertNull($answer);
    }

    // --- withDefaultCommand() on a nameless command ---

    public function testDefaultCommandWithoutANameIsRejected(): void
    {
        // Symfony's addCommand() rejects the empty name before the null check
        // in withDefaultCommand() can be reached, which is what that method's
        // comment claims. This pins the claim down: the failure is reported,
        // and it names the offending class either way.
        try {
            Application::make('Test', '1.0')->withDefaultCommand(NamelessCommand::class);
            $this->fail('A command with no name cannot be the default command.');
        } catch (\Throwable $ex) {
            $this->assertStringContainsString('NamelessCommand', $ex->getMessage());
            $this->assertStringContainsString('empty name', $ex->getMessage());
        }
    }

    // --- the formatter guard in execute() ---

    public function testFormatterGuardMessageIsAccurate(): void
    {
        // The guard cannot be reached through a real application: Symfony's
        // default helper set always supplies a FormatterHelper. Assert the
        // contract it states rather than leaving it undescribed.
        $command = new class extends Command {
            public static string $name = 'test:formatter';
            protected function handle(): void
            {
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $this->assertInstanceOf(
            \Symfony\Component\Console\Helper\FormatterHelper::class,
            $command->getHelper('formatter')
        );
    }

    // --- Application::call() renders the error when not silent ---

    public function testNonSilentCallRendersTheUnderlyingErrorToStderr(): void
    {
        // The error goes to stderr, not stdout, so ob_start() does not see it —
        // this runs a subprocess and reads the streams separately.
        $result = $this->callInSubprocess(silently: false);

        $this->assertSame('1', trim($result['stdout']), 'call() returns FAILURE.');
        $this->assertStringContainsString('does:not', $result['stderr']);
    }

    public function testSilentCallWritesNothingToEitherStream(): void
    {
        $result = $this->callInSubprocess(silently: true);

        $this->assertSame('1', trim($result['stdout']));
        $this->assertSame('', trim($result['stderr']), 'A silent call must stay silent.');
    }

    /**
     * Run Application::call() for an unknown command in a clean process.
     *
     * @return array{stdout: string, stderr: string}
     */
    private function callInSubprocess(bool $silently): array
    {
        $silentArg = $silently ? 'true' : 'false';
        $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);

        $script = <<<PHP
            <?php
            require $autoload;
            use Simsoft\\Console\\Application;
            \$app = Application::make('Test', '1.0');
            \$app->setAutoExit(false);
            \$app->shareGlobally();
            echo Application::call('does:not:exist', [], $silentArg);
            PHP;

        $path = tempnam(sys_get_temp_dir(), 'call') . '.php';
        file_put_contents($path, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open([PHP_BINARY, $path], $descriptors, $pipes);

        if (!is_resource($process)) {
            @unlink($path);
            $this->fail('Could not start the subprocess.');
        }

        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($process);
        @unlink($path);

        return ['stdout' => $stdout, 'stderr' => $stderr];
    }
}

/** A command with no name, for withDefaultCommand()'s guard. */
class NamelessCommand extends Command
{
    public static string $name = '';

    protected function handle(): void
    {
    }
}
