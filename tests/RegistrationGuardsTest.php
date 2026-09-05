<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\SimpleCommand;

/**
 * Guards on registration and dispatch.
 *
 * Each of these paths previously produced a raw TypeError from inside Symfony
 * or a fatal on null — the type was named but not the id, class, or call that
 * caused it. They now fail with a message that identifies the actual problem.
 */
class RegistrationGuardsTest extends TestCase
{
    protected function setUp(): void
    {
        Application::flushGlobal();

        $reflection = new ReflectionClass(Application::class);
        $reflection->getProperty('closureCommands')->setValue(null, []);
        $reflection->getProperty('commands')->setValue(null, []);
    }

    /**
     * @param array<string, mixed> $services
     */
    private function createContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, mixed> $services */
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

    // --- withCommands ---

    public function testWithCommandsRejectsAClassThatIsNotACommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a ' . Command::class);

        Application::make('Test', '1.0')->withCommands([\stdClass::class]);
    }

    public function testWithCommandsRejectsANonCommandEvenWhenNotLazyLoading(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a ' . Command::class);

        Application::make('Test', '1.0')->withCommands([\stdClass::class], false);
    }

    public function testWithCommandsNamesTheOffendingClass(): void
    {
        try {
            Application::make('Test', '1.0')->withCommands([\stdClass::class]);
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('stdClass', $e->getMessage());
        }
    }

    public function testWithCommandsAcceptsAValidCommand(): void
    {
        $app = Application::make('Test', '1.0')->withCommands([SimpleCommand::class]);

        $this->assertTrue($app->has('test:simple'));
    }

    // --- container resolution ---

    public function testContainerReturningANonCommandIsRejected(): void
    {
        $container = $this->createContainer(['some.service' => new \stdClass()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The container returned stdClass for "some.service"');

        Application::make('Test', '1.0')
            ->withContainer($container)
            ->withCommands(['some.service']);
    }

    public function testContainerResolvedCommandIsRegistered(): void
    {
        $container = $this->createContainer(['my.command' => new SimpleCommand()]);

        $app = Application::make('Test', '1.0')
            ->withContainer($container)
            ->withCommands(['my.command']);

        $this->assertTrue($app->has('test:simple'));
    }

    // --- withDefaultCommand ---

    public function testWithDefaultCommandRejectsAClassThatIsNotACommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The default command class must extend it');

        Application::make('Test', '1.0')->withDefaultCommand(\stdClass::class);
    }

    public function testWithDefaultCommandRejectsANonCommandFromTheContainer(): void
    {
        $container = $this->createContainer(['default.command' => new \stdClass()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The container returned stdClass');

        Application::make('Test', '1.0')
            ->withContainer($container)
            ->withDefaultCommand('default.command');
    }

    public function testWithDefaultCommandAcceptsAValidCommand(): void
    {
        $app = Application::make('Test', '1.0')->withDefaultCommand(SimpleCommand::class);

        $this->assertTrue($app->has('test:simple'));
    }

    // --- dispatch without an application ---

    public function testCallOnACommandWithNoApplicationExplainsItself(): void
    {
        $command = new SimpleCommand();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('call() requires an application');

        $command->call('test:other');
    }

    public function testCallSilentlyOnACommandWithNoApplicationExplainsItself(): void
    {
        $command = new SimpleCommand();

        // callSilently() reads output verbosity before dispatching, so give it
        // an output; the missing application is what should be reported.
        $reflection = new ReflectionClass($command);
        $property = $reflection->getParentClass()->getProperty('output');
        $property->setValue($command, new BufferedOutput());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('callSilently() requires an application');

        $command->callSilently('test:other');
    }

    public function testMissingApplicationErrorNamesTheCommandClass(): void
    {
        try {
            (new SimpleCommand())->call('test:other');
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(SimpleCommand::class, $e->getMessage());
        }
    }
}
