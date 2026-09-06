<?php

declare(strict_types=1);

namespace Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Fixtures\SimpleCommand;

/**
 * Regression tests for the design gaps found during review:
 *
 * - the DI container was unreachable from the static call() API
 * - static registration state went stale once call() cached its application
 * - uncaught throwables were reduced to a one-line message, losing the origin
 */
class ApplicationWiringTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetStatics();
    }

    protected function tearDown(): void
    {
        $this->resetStatics();
    }

    private function resetStatics(): void
    {
        Application::flushGlobal();

        $reflection = new ReflectionClass(Application::class);
        $reflection->getProperty('closureCommands')->setValue(null, []);
        $reflection->getProperty('commands')->setValue(null, []);
        $reflection->getProperty('lazyLoad')->setValue(null, true);
    }

    private function createContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id] ?? throw new RuntimeException("Service not found: $id");
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    // --- Container reachability from the static call() API ---

    public function testSharedApplicationMakesContainerReachableFromStaticCall(): void
    {
        $service = new class {
            public function hi(): string
            {
                return 'from-container';
            }
        };

        $command = new class extends Command {
            public static string $name = 'wiring:resolve';
            public static string $description = 'Resolve via static call';
            public static string $serviceClass = '';

            protected function handle(): void
            {
                $this->line('RESOLVED:' . $this->resolve(static::$serviceClass)->hi());
            }
        };

        $command::$serviceClass = get_class($service);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withContainer($this->createContainer([get_class($service) => $service]));
        $app->addCommand($command);
        $app->shareGlobally();

        $this->assertSame(
            0,
            Application::call('wiring:resolve'),
            'A command resolving a container service failed when invoked through Application::call().'
        );
    }

    public function testShareGloballyReturnsSelf(): void
    {
        $app = Application::make('Test', '1.0');

        $this->assertSame($app, $app->shareGlobally());
    }

    public function testSharedApplicationExposesItsScheduler(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withScheduler(function ($scheduler): void {
            $scheduler->command('wiring:task')->daily();
        });
        $app->shareGlobally();

        $this->assertSame(
            0,
            Application::call('schedule:list'),
            'schedule:list was unreachable through Application::call() on a shared application.'
        );
    }

    public function testFlushGlobalDropsTheSharedApplication(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand(new SimpleCommand());
        $app->shareGlobally();

        $this->assertSame(0, Application::call('test:simple'));

        Application::flushGlobal();

        $this->assertSame(
            1,
            Application::call('test:simple'),
            'flushGlobal() left the shared application in place.'
        );
    }

    public function testResolveWithoutContainerExplainsHowToShareTheApplication(): void
    {
        $command = new class extends Command {
            public static string $name = 'wiring:unshared';
            public static string $description = 'Resolve without a container';

            protected function handle(): void
            {
                $this->resolve('SomeService');
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $status = $app->doRun(new ArrayInput(['command' => 'wiring:unshared']), $output);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'shareGlobally',
            $output->fetch(),
            'The container-less resolve() failure gave no hint about how to fix it.'
        );
    }

    // --- Static registration state must not go stale ---

    public function testCommandsRegisteredAfterAFirstCallAreStillReachable(): void
    {
        $first = new class extends Command {
            public static string $name = 'wiring:first';
            public static string $description = 'First';

            protected function handle(): void
            {
                $this->line('FIRST');
            }
        };

        $second = new class extends Command {
            public static string $name = 'wiring:second';
            public static string $description = 'Second';

            protected function handle(): void
            {
                $this->line('SECOND');
            }
        };

        Application::commands([get_class($first)], false);
        $this->assertSame(0, Application::call('wiring:first'));

        // The cached application must not shadow this later registration.
        Application::commands([get_class($second)], false);

        $this->assertSame(
            0,
            Application::call('wiring:second'),
            'A command registered after the first Application::call() was silently ignored.'
        );
    }

    public function testClosureCommandRegisteredAfterAFirstCallIsStillReachable(): void
    {
        Application::commands([SimpleCommand::class], false);
        $this->assertSame(0, Application::call('test:simple'));

        Application::command('wiring:late', function (): void {
        });

        $this->assertSame(
            0,
            Application::call('wiring:late'),
            'A closure command registered after the first Application::call() was unreachable.'
        );
    }

    public function testClosureCommandLoaderCanBeBuiltRepeatedly(): void
    {
        Application::command('wiring:repeat', function (): void {
        });

        Application::getClosureCommandLoader();
        $second = Application::getClosureCommandLoader();

        // has() only checks for the key, so the command must actually be built:
        // rewriting the static array in place produced factories that closed
        // over an undefined builder and blew up here.
        $this->assertTrue($second->has('wiring:repeat'));
        $this->assertSame(
            'wiring:repeat',
            $second->get('wiring:repeat')->getName(),
            'Rebuilding the closure command loader produced unusable factories.'
        );
    }

    public function testStaticCallReportsAnUnknownCommandInsteadOfFailingSilently(): void
    {
        Application::commands([SimpleCommand::class], false);

        [$status, $rendered] = $this->callInSubprocess(silently: false);

        $this->assertSame(1, $status);
        // Symfony reports an unresolvable name at the namespace level here.
        $this->assertStringContainsString(
            'wiring',
            $rendered,
            'A failed Application::call() returned 1 without saying what went wrong.'
        );
    }

    public function testSilentStaticCallStaysSilentOnFailure(): void
    {
        Application::commands([SimpleCommand::class], false);

        [$status, $rendered] = $this->callInSubprocess(silently: true);

        $this->assertSame(1, $status);
        $this->assertSame(
            '',
            $rendered,
            'A silent Application::call() printed an exception block.'
        );
    }

    /**
     * Run Application::call() in a subprocess and capture its stderr.
     *
     * call() builds its own ConsoleOutput against php://stderr, so the rendered
     * throwable bypasses PHPUnit's output buffering entirely. Only a real
     * separate process can observe what a caller would actually see.
     *
     * @param bool $silently Passed through to Application::call().
     * @return array{0: int, 1: string} Exit code and captured stderr.
     */
    private function callInSubprocess(bool $silently): array
    {
        $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);
        $silentlyArg = var_export($silently, true);

        $script = <<<PHP
        <?php
        require $autoload;
        use Simsoft\\Console\\Application;
        use Tests\\Fixtures\\SimpleCommand;

        Application::commands([SimpleCommand::class], false);
        exit(Application::call('wiring:does-not-exist', [], $silentlyArg));
        PHP;

        $path = tempnam(sys_get_temp_dir(), 'call') . '.php';
        file_put_contents($path, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            [PHP_BINARY, $path],
            $descriptors,
            $pipes,
            dirname(__DIR__)
        );

        $this->assertIsResource($process, 'Failed to start the subprocess.');

        stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        @unlink($path);

        return [$status, $stderr];
    }

    // --- Throwable reporting keeps the origin ---

    public function testCommandFailureShowsOnlyTheMessageAtNormalVerbosity(): void
    {
        $output = $this->runFailingCommand(OutputInterface::VERBOSITY_NORMAL);

        $this->assertStringContainsString('boom', $output);
        $this->assertStringNotContainsString(
            'RuntimeException',
            $output,
            'Normal verbosity leaked exception internals into ordinary output.'
        );
    }

    public function testVerboseOutputIncludesExceptionClassAndOrigin(): void
    {
        $output = $this->runFailingCommand(OutputInterface::VERBOSITY_VERBOSE);

        $this->assertStringContainsString('boom', $output);
        $this->assertStringContainsString(
            'RuntimeException',
            $output,
            'The exception class was lost at -v.'
        );
        $this->assertStringContainsString(
            'ApplicationWiringTest.php',
            $output,
            'The file the exception came from was lost at -v.'
        );
    }

    public function testVerboseOutputIncludesPreviousExceptions(): void
    {
        $output = $this->runFailingCommand(OutputInterface::VERBOSITY_VERBOSE);

        $this->assertStringContainsString('Caused by', $output);
        $this->assertStringContainsString(
            'underlying cause',
            $output,
            'The previous exception was discarded.'
        );
    }

    public function testVeryVerboseOutputIncludesTheStackTrace(): void
    {
        $output = $this->runFailingCommand(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $this->assertStringContainsString(
            '#0',
            $output,
            'No stack trace was rendered at -vv.'
        );
    }

    public function testVerboseFailureStillReturnsFailureExitCode(): void
    {
        $command = $this->failingCommand();

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $status = $app->doRun(new ArrayInput(['command' => 'wiring:fail']), $output);

        $this->assertSame(1, $status);
    }

    private function failingCommand(): Command
    {
        return new class extends Command {
            public static string $name = 'wiring:fail';
            public static string $description = 'Always fails';

            protected function handle(): void
            {
                throw new RuntimeException('boom', 0, new LogicException('underlying cause'));
            }
        };
    }

    private function runFailingCommand(int $verbosity): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($this->failingCommand());

        $output = new BufferedOutput($verbosity);
        $app->doRun(new ArrayInput(['command' => 'wiring:fail']), $output);

        return $output->fetch();
    }
}
