<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\ArgumentCommand;
use Tests\Fixtures\SimpleCommand;

class ApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static state between tests
        $reflection = new \ReflectionClass(Application::class);

        $closureCommands = $reflection->getProperty('closureCommands');
        $closureCommands->setValue(null, []);

        $commands = $reflection->getProperty('commands');
        $commands->setValue(null, []);

        $app = $reflection->getProperty('app');
        $app->setValue(null, null);

        $lazyLoad = $reflection->getProperty('lazyLoad');
        $lazyLoad->setValue(null, true);
    }

    // --- Factory Make ---

    public function testMakeReturnsApplicationInstance(): void
    {
        $app = Application::make();
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testMakeWithDefaultNameAndVersion(): void
    {
        $app = Application::make();
        $this->assertSame('Console App', $app->getName());
        $this->assertSame('1.0', $app->getVersion());
    }

    public function testMakeWithCustomNameAndVersion(): void
    {
        $app = Application::make('My App', '2.5');
        $this->assertSame('My App', $app->getName());
        $this->assertSame('2.5', $app->getVersion());
    }

    // --- withCommands (lazy load) ---

    public function testWithCommandsLazyLoad(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $result = $app->withCommands([SimpleCommand::class]);

        $this->assertInstanceOf(Application::class, $result);
        $this->assertTrue($app->has('test:simple'));
    }

    public function testWithCommandsEagerLoad(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $result = $app->withCommands([SimpleCommand::class], false);

        $this->assertInstanceOf(Application::class, $result);
        $this->assertTrue($app->has('test:simple'));
    }

    public function testWithCommandsReturnsSelf(): void
    {
        $app = Application::make('Test', '1.0');
        $result = $app->withCommands([]);
        $this->assertSame($app, $result);
    }

    public function testWithCommandsEmptyArray(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $result = $app->withCommands([]);
        $this->assertInstanceOf(Application::class, $result);
    }

    public function testWithMultipleCommands(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class, ArgumentCommand::class]);

        $this->assertTrue($app->has('test:simple'));
        $this->assertTrue($app->has('test:args'));
    }

    // --- withDefaultCommand ---

    public function testWithDefaultCommand(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $result = $app->withDefaultCommand(SimpleCommand::class);

        $this->assertInstanceOf(Application::class, $result);
        $this->assertTrue($app->has('test:simple'));
    }

    // --- Closure Commands ---

    public function testClosureCommandRegistration(): void
    {
        Application::command('test:closure', function () {
            $this->info('Closure works');
        });

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);

        $input = new ArrayInput(['command' => 'test:closure']);
        $output = new BufferedOutput();
        $app->run($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('Closure works', $result);
    }

    public function testClosureCommandWithPurpose(): void
    {
        Application::command('test:purpose', function () {
            $this->info('Has purpose');
        })->purpose('A test purpose');

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);

        $input = new ArrayInput(['command' => 'test:purpose']);
        $output = new BufferedOutput();
        $app->run($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('Has purpose', $result);
    }

    public function testClosureCommandWithInput(): void
    {
        Application::command('test:input-closure', function () {
            $name = $this->argument('name');
            $this->line("NAME:$name");
        })->purpose('Input test')->input(function () {
            $this->addArgument('name', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        });

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);

        $input = new ArrayInput(['command' => 'test:input-closure', 'name' => 'TestUser']);
        $output = new BufferedOutput();
        $app->run($input, $output);

        $result = $output->fetch();
        $this->assertStringContainsString('NAME:TestUser', $result);
    }

    // --- Static commands() registration ---

    public function testStaticCommandsRegistration(): void
    {
        Application::commands([SimpleCommand::class]);

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);

        // Use the static call method
        $status = Application::call('test:simple', [], true);
        $this->assertSame(0, $status);
    }

    public function testStaticCommandsWithLazyLoadDisabled(): void
    {
        Application::commands([SimpleCommand::class], false);

        $status = Application::call('test:simple', [], true);
        $this->assertSame(0, $status);
    }

    // --- Static call() ---

    public function testStaticCallExecutesCommand(): void
    {
        Application::commands([SimpleCommand::class]);
        $status = Application::call('test:simple');
        $this->assertSame(0, $status);
    }

    public function testStaticCallWithArguments(): void
    {
        Application::commands([ArgumentCommand::class]);
        $status = Application::call('test:args', ['name' => 'StaticCall']);
        $this->assertSame(0, $status);
    }

    public function testStaticCallNonExistentCommandReturnsError(): void
    {
        Application::commands([SimpleCommand::class]);
        $status = Application::call('nonexistent:command');
        $this->assertSame(1, $status);
    }

    // --- Run method ---

    public function testRunWithClassBasedCommands(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->withCommands([SimpleCommand::class]);

        $input = new ArrayInput(['command' => 'test:simple']);
        $output = new BufferedOutput();
        $status = $app->run($input, $output);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Simple command executed', $output->fetch());
    }
}
