<?php

namespace Simsoft\Console;

use Closure;
use Psr\Container\ContainerInterface;
use Simsoft\Console\Commands\ScheduleListCommand;
use Simsoft\Console\Commands\ScheduleRunCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
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

    /** @var Application|null Auto-built instance backing static call(). */
    protected static ?Application $app = null;

    /** @var Application|null Instance explicitly shared via shareGlobally(). */
    protected static ?Application $sharedApp = null;

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
        if (static::$sharedApp instanceof static) {
            return static::$sharedApp;
        }

        if (static::$app === null) {
            static::$app = static::make();
            static::$app->setAutoExit(false);

            if (static::$closureCommands) {
                static::$app->setCommandLoader(static::getClosureCommandLoader());
            }

            static::$app->withCommands(static::$commands, static::$lazyLoad);
        }
        return static::$app;
    }

    /**
     * Share this instance with the static call() API.
     *
     * Without this, static::call() builds its own bare application, which has
     * no container and no scheduler. Commands invoked through it would fail to
     * resolve services that work fine under run(). Sharing the configured
     * instance makes both entry points behave identically.
     *
     * @return $this
     */
    public function shareGlobally(): static
    {
        static::$sharedApp = $this;
        return $this;
    }

    /**
     * Drop the shared instance and the auto-built one.
     *
     * Mainly useful in tests and long-running workers, where leftover static
     * state would otherwise leak between runs.
     *
     * @return void
     */
    public static function flushGlobal(): void
    {
        static::$sharedApp = null;
        static::$app = null;
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
        static::$app = null;

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

        // The auto-built application caches whatever was registered at the time
        // of the first call(). Discard it so a later registration is not
        // silently ignored.
        static::$app = null;
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
        $output = new ConsoleOutput(
            $silently ? OutputInterface::VERBOSITY_QUIET : OutputInterface::VERBOSITY_NORMAL
        );

        try {
            return static::getApplication()->doRun(
                new ArrayInput(array_merge(['command' => $commandName], $input)),
                $output,
            );

        } catch (Throwable $throwable) {
            // An unknown command or a failure inside doRun() itself. Returning
            // a bare 1 with no message left callers with nothing to debug.
            // Symfony's renderer writes at quiet level, so it would punch
            // through a silent call — honour $silently and stay quiet there.
            if (!$silently) {
                static::getApplication()->renderThrowable($throwable, static::errorOutput($output));
            }
        }

        return ConsoleCommand::FAILURE;
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
        $factories = [];

        foreach (static::$closureCommands as $name => $entry) {
            // Accept both raw builders and already-wrapped factories, so the
            // loader can be rebuilt any number of times. Rewriting the static
            // array in place made a second call return an empty loader.
            $factories[$name] = $entry instanceof Closure
                ? $entry
                : static fn(): Command => $entry->build();
        }

        return new FactoryCommandLoader($factories);
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

            if (static::$closureCommands) {
                $this->setCommandLoader(static::getClosureCommandLoader());
            }

            return parent::run($input, $output);
        } catch (Throwable $throwable) {
            // Symfony renders exceptions thrown inside a command itself, so
            // reaching here means the failure escaped that handling. Render it
            // rather than exiting silently with no explanation.
            $this->renderThrowable($throwable, static::errorOutput($output));
        }

        return ConsoleCommand::FAILURE;
    }

    /**
     * Get the stderr stream for an output, falling back to the output itself.
     *
     * Only ConsoleOutputInterface exposes getErrorOutput(); a BufferedOutput
     * or NullOutput does not.
     *
     * @param OutputInterface|null $output
     * @return OutputInterface
     */
    protected static function errorOutput(?OutputInterface $output): OutputInterface
    {
        $output ??= new ConsoleOutput();

        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
