<?php

namespace Simsoft\Console;

use Closure;
use Psr\Container\ContainerInterface;
use Simsoft\Console\Commands\ScheduleListCommand;
use Simsoft\Console\Commands\ScheduleRunCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class Application
 *
 * Console application.
 */
class Application extends ConsoleApplication
{
    /** @var array Closure commands */
    protected static array $closureCommands = [];

    /** @var array Command class */
    protected static array $commands = [];

    /** @var bool Enable lazy load commands. */
    protected static bool $lazyLoad = true;

    /** @var Application|null For closure command. */
    protected static ?Application $app = null;

    /** @var ContainerInterface|null PSR-11 DI container. */
    protected ?ContainerInterface $container = null;

    /** @var Scheduler|null Task scheduler. */
    protected ?Scheduler $scheduler = null;

    /**
     * Factory make.
     *
     * @param string $name The app name.
     * @param string $version The app version.
     * @return static
     */
    public static function make(string $name = 'Console App', string $version = '1.0'): static
    {
        return new static($name, $version);
    }

    /**
     * Make application for closure command call.
     *
     * @return static
     */
    protected static function getApplication(): static
    {
        if (static::$app === null) {
            static::$app = static::make();
            static::$app->setAutoExit(false);

            if (!current(static::$closureCommands) instanceof Closure) {
                static::$app->setCommandLoader(static::getClosureCommandLoader());
            }

            if (static::$commands && static::$lazyLoad) {
                foreach (static::$commands as $commandClass) {
                    static::$app->addCommand(forward_static_call([$commandClass, 'getLazyCommand']));
                }
            }

            if (static::$commands && !static::$lazyLoad) {
                foreach (static::$commands as $commandClass) {
                    static::$app->addCommand(new $commandClass());
                }
            }
        }
        return static::$app;
    }

    /**
     * Set a PSR-11 compatible DI container.
     *
     * Commands will be resolved from the container when available.
     *
     * @param ContainerInterface $container
     * @return $this
     */
    public function withContainer(ContainerInterface $container): static
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Get the DI container.
     *
     * @return ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * Set up the scheduler with a configuration callback.
     *
     * @param Closure $callback Receives a Scheduler instance.
     * @return $this
     */
    public function withScheduler(Closure $callback): static
    {
        $this->scheduler = new Scheduler();
        $callback($this->scheduler);

        $this->addCommand(new ScheduleRunCommand($this->scheduler));
        $this->addCommand(new ScheduleListCommand($this->scheduler));

        return $this;
    }

    /**
     * Get the scheduler instance.
     *
     * @return Scheduler|null
     */
    public function getScheduler(): ?Scheduler
    {
        return $this->scheduler;
    }

    /**
     * Register a Closure based command with the application.
     *
     * @param string $name
     * @param Closure $callback
     * @return CommandBuilder
     */
    public static function command(string $name, Closure $callback): CommandBuilder
    {
        return static::$closureCommands[$name] = new CommandBuilder($name, $callback);
    }

    /**
     * Register command classes.
     *
     * @param array $commandClass
     * @param bool $lazyLoad
     * @return void
     */
    public static function commands(array $commandClass, bool $lazyLoad = true): void
    {
        static::$commands = $commandClass;
        static::$lazyLoad = $lazyLoad;
    }

    /**
     *  Call command.
     *
     * @param string $commandName
     * @param array $input
     * @param bool $silently
     * @return int
     */
    public static function call(string $commandName, array $input = [], bool $silently = true): int
    {
        try {
            return static::getApplication()->doRun(
                new ArrayInput(array_merge(['command' => $commandName], $input)),
                new ConsoleOutput($silently ? OutputInterface::VERBOSITY_QUIET : OutputInterface::VERBOSITY_NORMAL),
            );

        } catch (Throwable) {
            // Command not found or execution error — return failure code
        }
        return 1;
    }

    /**
     * Run console commands
     *
     * @param string[] $commandClasses Command classes.
     * @param bool $lazyLoad Enable lazy command. Default: true.
     * @return static
     */
    public function withCommands(array $commandClasses = [], bool $lazyLoad = true): static
    {
        foreach ($commandClasses as $commandClass) {
            if ($this->container?->has($commandClass)) {
                $this->addCommand($this->container->get($commandClass));
                continue;
            }

            if ($lazyLoad) {
                $this->addCommand(forward_static_call([$commandClass, 'getLazyCommand']));
                continue;
            }

            $this->addCommand(new $commandClass());
        }

        return $this;
    }

    /**
     * Set default command.
     *
     * @param string $commandClass
     * @return $this
     */
    public function withDefaultCommand(string $commandClass): static
    {
        if ($this->container?->has($commandClass)) {
            /** @var Command $command */
            $command = $this->container->get($commandClass);
            $this->addCommand($command);
            return $this->setDefaultCommand($command->getName());
        }

        /** @var Command $command */
        $command = new $commandClass();
        $this->addCommand($command);
        return $this->setDefaultCommand($command->getName());
    }

    /**
     * Get closure command loader.
     *
     * @return FactoryCommandLoader
     */
    public static function getClosureCommandLoader(): FactoryCommandLoader
    {
        array_walk(static::$closureCommands, function ($builder, $name) {
            static::$closureCommands[$name] = function () use ($builder): Command {
                return $builder->build();
            };
        });
        return new FactoryCommandLoader(static::$closureCommands);
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        try {

            if (!current(static::$closureCommands) instanceof Closure) {
                $this->setCommandLoader(static::getClosureCommandLoader());
            }

            return parent::run($input, $output);
        } catch (Throwable) {
            // Unrecoverable error — return failure code
        }
        return 0;
    }
}
