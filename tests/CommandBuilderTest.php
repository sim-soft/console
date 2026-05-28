<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\ClosureCommand;
use Simsoft\Console\CommandBuilder;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandBuilderTest extends TestCase
{
    // --- Constructor ---

    public function testConstructorSetsNameAndCallback(): void
    {
        $callback = function () {
        };
        $builder = new CommandBuilder('test:builder', $callback);

        $command = $builder->build();
        $this->assertSame('test:builder', $command->getName());
    }

    // --- purpose() ---

    public function testPurposeSetsDescription(): void
    {
        $builder = new CommandBuilder('test:desc', function () {
        });
        $result = $builder->purpose('My description');

        $this->assertSame($builder, $result); // fluent
        $command = $builder->build();
        $this->assertSame('My description', $command->getDescription());
    }

    public function testPurposeWithEmptyString(): void
    {
        $builder = new CommandBuilder('test:empty-desc', function () {
        });
        $builder->purpose('');

        $command = $builder->build();
        $this->assertSame('', $command->getDescription());
    }

    // --- input() ---

    public function testInputSetsInputCallback(): void
    {
        $builder = new CommandBuilder('test:input', function () {
        });
        $result = $builder->input(function () {
            $this->addArgument('name');
        });

        $this->assertSame($builder, $result); // fluent
    }

    public function testInputCallbackIsAppliedOnBuild(): void
    {
        $builder = new CommandBuilder('test:input-build', function () {
        });
        $builder->input(function () {
            $this->addArgument('username', InputArgument::REQUIRED);
        });

        $command = $builder->build();
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('username'));
    }

    // --- build() ---

    public function testBuildReturnsClosureCommand(): void
    {
        $builder = new CommandBuilder('test:build', function () {
        });
        $command = $builder->build();

        $this->assertInstanceOf(ClosureCommand::class, $command);
    }

    public function testBuildSetsCommandName(): void
    {
        $builder = new CommandBuilder('test:name-check', function () {
        });
        $command = $builder->build();

        $this->assertSame('test:name-check', $command->getName());
    }

    public function testBuildSetsDescription(): void
    {
        $builder = new CommandBuilder('test:desc-check', function () {
        });
        $builder->purpose('Built description');
        $command = $builder->build();

        $this->assertSame('Built description', $command->getDescription());
    }

    public function testBuildSetsHandler(): void
    {
        $builder = new CommandBuilder('test:handler-check', function () {
            $this->line('HANDLER_WORKS');
        });

        $command = $builder->build();

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:handler-check']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('HANDLER_WORKS', $output->fetch());
    }

    public function testBuildWithoutInputCallback(): void
    {
        $builder = new CommandBuilder('test:no-input', function () {
        });
        $command = $builder->build();

        // Should not throw, inputCallback is null
        $this->assertInstanceOf(ClosureCommand::class, $command);
    }

    // --- Fluent chaining ---

    public function testFluentChaining(): void
    {
        $builder = new CommandBuilder('test:fluent', function () {
        });

        $result = $builder
            ->purpose('Fluent test')
            ->input(function () {
                $this->addArgument('arg');
            });

        $this->assertSame($builder, $result);
    }

    // --- Multiple builds produce independent commands ---

    public function testMultipleBuildsAreIndependent(): void
    {
        $builder1 = new CommandBuilder('test:ind-one', function () {
            $this->line('ONE');
        });
        $builder1->purpose('First');

        $builder2 = new CommandBuilder('test:ind-two', function () {
            $this->line('TWO');
        });
        $builder2->purpose('Second');

        $cmd1 = $builder1->build();
        $cmd2 = $builder2->build();

        $this->assertSame('test:ind-one', $cmd1->getName());
        $this->assertSame('First', $cmd1->getDescription());
        $this->assertSame('test:ind-two', $cmd2->getName());
        $this->assertSame('Second', $cmd2->getDescription());
    }
}
