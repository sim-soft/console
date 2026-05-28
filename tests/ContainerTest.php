<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        $closureCommands = $reflection->getProperty('closureCommands');
        $closureCommands->setValue(null, []);
        $commands = $reflection->getProperty('commands');
        $commands->setValue(null, []);
        $app = $reflection->getProperty('app');
        $app->setValue(null, null);
    }

    private function createContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id] ?? throw new \RuntimeException("Service not found: $id");
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    // --- withContainer ---

    public function testWithContainerReturnsSelf(): void
    {
        $container = $this->createContainer([]);
        $app = Application::make('Test', '1.0');
        $result = $app->withContainer($container);
        $this->assertSame($app, $result);
    }

    public function testGetContainerReturnsContainer(): void
    {
        $container = $this->createContainer([]);
        $app = Application::make('Test', '1.0');
        $app->withContainer($container);
        $this->assertSame($container, $app->getContainer());
    }

    public function testGetContainerReturnsNullWithoutSetup(): void
    {
        $app = Application::make('Test', '1.0');
        $this->assertNull($app->getContainer());
    }

    // --- Commands resolved from container ---

    public function testCommandResolvedFromContainer(): void
    {
        $command = new class extends Command {
            static string $name = 'test:injected';
            static string $description = 'Injected command';
            private string $greeting;

            public function setGreeting(string $greeting): void
            {
                $this->greeting = $greeting;
            }

            protected function handle(): void
            {
                $this->line('GREETING:' . $this->greeting);
            }
        };

        $command->setGreeting('Hello from DI');

        $container = $this->createContainer([
            get_class($command) => $command,
        ]);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withContainer($container);
        $app->withCommands([get_class($command)]);

        $input = new ArrayInput(['command' => 'test:injected']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('GREETING:Hello from DI', $output->fetch());
    }

    public function testCommandFallsBackWithoutContainer(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([\Tests\Fixtures\SimpleCommand::class]);

        $this->assertTrue($app->has('test:simple'));
    }

    public function testCommandFallsBackWhenNotInContainer(): void
    {
        $container = $this->createContainer([]); // Empty container

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withContainer($container);
        $app->withCommands([\Tests\Fixtures\SimpleCommand::class]);

        $this->assertTrue($app->has('test:simple'));
    }

    // --- withDefaultCommand from container ---

    public function testDefaultCommandResolvedFromContainer(): void
    {
        $command = new class extends Command {
            static string $name = 'test:default-di';
            static string $description = 'Default DI command';

            protected function handle(): void
            {
                $this->line('DEFAULT_DI');
            }
        };

        $container = $this->createContainer([
            get_class($command) => $command,
        ]);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withContainer($container);
        $app->withDefaultCommand(get_class($command));

        $this->assertTrue($app->has('test:default-di'));
    }

    // --- resolve() from within a command ---

    public function testResolveServiceFromCommand(): void
    {
        $service = new class {
            public function greet(): string
            {
                return 'Hello from service';
            }
        };

        $serviceClass = get_class($service);

        $command = new class extends Command {
            static string $name = 'test:resolve';
            static string $description = 'Resolve test';
            public static string $serviceClass = '';

            protected function handle(): void
            {
                $svc = $this->resolve(static::$serviceClass);
                $this->line('RESOLVED:' . $svc->greet());
            }
        };

        $command::$serviceClass = $serviceClass;

        $container = $this->createContainer([
            $serviceClass => $service,
        ]);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withContainer($container);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:resolve']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('RESOLVED:Hello from service', $output->fetch());
    }

    public function testResolveThrowsWhenServiceNotFound(): void
    {
        $command = new class extends Command {
            static string $name = 'test:resolve-fail';
            static string $description = 'Resolve fail test';

            protected function handle(): void
            {
                $this->resolve('NonExistentService');
            }
        };

        $container = $this->createContainer([]);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withContainer($container);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:resolve-fail']);
        $output = new BufferedOutput();
        $status = $app->doRun($input, $output);

        // Should fail because resolve throws RuntimeException
        $this->assertSame(1, $status);
    }

    public function testHasServiceReturnsTrue(): void
    {
        $service = new \stdClass();

        $command = new class extends Command {
            static string $name = 'test:has-service';
            static string $description = 'Has service test';

            protected function handle(): void
            {
                $has = $this->hasService(\stdClass::class);
                $this->line('HAS:' . ($has ? 'yes' : 'no'));
            }
        };

        $container = $this->createContainer([
            \stdClass::class => $service,
        ]);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withContainer($container);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:has-service']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('HAS:yes', $output->fetch());
    }

    public function testHasServiceReturnsFalseWithoutContainer(): void
    {
        $command = new class extends Command {
            static string $name = 'test:no-container';
            static string $description = 'No container test';

            protected function handle(): void
            {
                $has = $this->hasService('SomeService');
                $this->line('HAS:' . ($has ? 'yes' : 'no'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:no-container']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('HAS:no', $output->fetch());
    }
}
