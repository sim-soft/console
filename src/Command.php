<?php

namespace Simsoft\Console;

use Countable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\{FormatterHelper,
    HelperInterface,
    ProgressBar,
    ProgressIndicator,
    QuestionHelper,
    Table,
    TreeHelper,
    TreeStyle};
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Throwable;

/**
 * Class Command
 *
 * @package Simsoft\Console
 *
 * @author: vzangloo <vzangloo@7mayday.com>
 * @since 1.0.0
 */
abstract class Command extends ConsoleCommand
{
    use LockableTrait;

    /** @var InputInterface Command input. */
    protected InputInterface $input;

    /** @var OutputInterface Command output. */
    protected OutputInterface $output;

    /** @var FormatterHelper Command output formatter. */
    protected HelperInterface $formatter;

    /** @var bool Enable command lock to prevent parallel execution. */
    protected bool $lockable = false;

    /** @var string Command name. */
    public static string $name = '';

    /** @var string Command description. */
    public static string $description = '';

    /** @var bool Display datetime in message. */
    protected bool $messageTimeStamp = true;

    public const MESSAGE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Actual execution.
     *
     * @return void
     */
    abstract protected function handle(): void;

    /**
     * @return void
     */
    protected function configure(): void
    {
        if (static::$name) {
            $this->setName(static::$name);
        }

        $this->setDescription(static::$description);

        $this->init();
    }

    /**
     * Initialization.
     *
     * @return void
     */
    protected function init(): void
    {

    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->formatter = $this->getHelper('formatter');

        if ($this->lockable && !$this->lock()) {
            $this->comment('The command is already running in another process.');
            return ConsoleCommand::SUCCESS;
        }

        try {
            $this->handle();
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());
            return ConsoleCommand::FAILURE;
        } finally {
            if ($this->lockable) {
                $this->release();
            }
        }

        return ConsoleCommand::SUCCESS;
    }

    /**
     * Run with progress bar
     *
     * @param Countable|iterable $data
     * @param callable $callback A callable to handle each data.
     * @param int $maxSteps
     * @return void
     */
    public function withProgressBar(Countable|iterable $data, callable $callback, int $maxSteps = 0): void
    {
        if (is_iterable($data)) {
            $data = iterator_to_array($data);
        }

        $progress = new ProgressBar($this->output, count($data));

        if ($maxSteps) {
            $progress->setMaxSteps($maxSteps);
        }

        $progress->start();

        foreach ($data as $key => $value) {
            $callback($value, $key);
            $progress->advance();
        }

        $progress->finish();
    }

    /**
     * Create a progress bar.
     *
     * @param int $max
     * @return ProgressBar
     */
    public function createProgressBar(int $max): ProgressBar
    {
        return new ProgressBar($this->output, $max);
    }

    /**
     * Create a progress indicator for indeterminate tasks.
     *
     * @param string|null $format Display format (null for auto-detect).
     * @param int $indicatorChangeInterval Change interval in milliseconds.
     * @param array|null $indicatorValues Animated indicator characters.
     * @return ProgressIndicator
     */
    public function createProgressIndicator(
        ?string $format = null,
        int     $indicatorChangeInterval = 100,
        ?array  $indicatorValues = null,
    ): ProgressIndicator
    {
        return new ProgressIndicator($this->output, $format, $indicatorChangeInterval, $indicatorValues);
    }

    /**
     * Render a tree structure to the output.
     *
     * @param string $root Root node label.
     * @param iterable $values Tree data (nested arrays or TreeNode instances).
     * @param TreeStyle|null $style Tree style (null for default).
     * @return void
     */
    public function tree(string $root, iterable $values, ?TreeStyle $style = null): void
    {
        TreeHelper::createTree($this->output, $root, $values, $style)->render();
    }

    /**
     * Display data in table.
     *
     * @param array $headers Table headers.
     * @param iterable $data 2 dimensional array data to be displayed.
     * @param callable|null $closure A closure to return array of data.
     * @return void
     * @throws InvalidArgumentException
     */
    public function table(array $headers, iterable $data, ?callable $closure = null): void
    {
        $table = new Table($this->output);
        $table->setHeaders($headers);

        if (is_callable($closure)) {
            foreach ($data as $row) {
                $table->addRow($closure($row));
            }
            $table->render();
            return;
        }

        foreach ($data as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("Each row should be an array.");
            }
            $table->addRow($row);
        }

        $table->render();
    }

    /**
     * Display a formatted line with type styling.
     *
     * @param string $type The format type (info, comment, question, error).
     * @param string $message The message to display.
     * @return void
     */
    public function formattedLine(string $type, string $message): void
    {
        $this->output->writeln(
            $this->formatter->formatSection(
                $this->messageTimeStamp ? $this->getCurrentDatetime() : strtoupper($type),
                "<$type>$message</$type>"
            )
        );
    }

    /**
     * Display simple info message.
     *
     * @param string $message The info message to be displayed.
     * @return void
     */
    public function info(string $message): void
    {
        $this->formattedLine('info', $message);
    }

    /**
     * Display comment message.
     *
     * @param string $message The comment message to be displayed.
     * @return void
     */
    public function comment(string $message): void
    {
        $this->formattedLine('comment', $message);
    }

    /**
     * Display comment message.
     *
     * @param string $message The comment message to be displayed.
     * @return void
     */
    public function question(string $message): void
    {
        $this->formattedLine('question', $message);
    }

    /**
     * Display simple error message.
     *
     * @param string $message The error message to be displayed.
     * @return void
     */
    public function error(string $message): void
    {
        $this->formattedLine('error', $message);
    }

    /**
     * Display uncolored text.
     *
     * @param string $message The message to be displayed.
     * @return void
     */
    public function line(string $message): void
    {
        $this->output->writeln(($this->messageTimeStamp ? '[' . $this->getCurrentDatetime() . '] ': '') . $message);
    }

    /**
     * Display blank lines.
     *
     * @param int $repeat Total
     * @return void
     */
    public function newLine(int $repeat = 0): void
    {
        do {
            $this->line('');
        } while (--$repeat > 0);
    }

    /**
     * Display error block.
     *
     * @param string $section Section Name.
     * @param string $message Message name.
     * @param bool $labelOff
     * @return void
     */
    public function errorBlock(string $section, string $message, bool $labelOff = false): void
    {
        $label = $this->messageTimeStamp && !$labelOff? $this->getCurrentDatetime() . '] ': '';

        $this->output->writeln(
            $this->formatter->formatBlock([$label.$section, $label.$message], 'error')
        );
    }

    /**
     * Get all arguments.
     *
     * @return array
     */
    public function arguments(): array
    {
        return $this->input->getArguments();
    }

    /**
     * Get the named argument.
     *
     * @param string $name Name of the argument.
     * @param mixed|null $default
     * @return mixed
     */
    public function argument(string $name, mixed $default = null): mixed
    {
        return $this->input->getArgument($name) ?? $default;
    }

    /**
     * Get all options.
     *
     * @return array
     */
    public function options(): array
    {
        return $this->input->getOptions();
    }

    /**
     * Get the named option.
     *
     * @param string $name Name of the option.
     * @param mixed|null $default
     * @return mixed
     */
    public function option(string $name, mixed $default = null): mixed
    {
        return $this->input->getOption($name) ?? $default;
    }

    /**
     * Resolve a service from the DI container.
     *
     * @template T
     * @param class-string<T> $id Service identifier or class name.
     * @return T|mixed
     * @throws RuntimeException If no container is available.
     */
    public function resolve(string $id): mixed
    {
        $app = $this->getApplication();

        if ($app instanceof Application && $app->getContainer()?->has($id)) {
            return $app->getContainer()->get($id);
        }

        throw new RuntimeException("Unable to resolve '$id' — no container configured or service not found.");
    }

    /**
     * Check if a service exists in the DI container.
     *
     * @param string $id Service identifier or class name.
     * @return bool
     */
    public function hasService(string $id): bool
    {
        $app = $this->getApplication();

        if ($app instanceof Application) {
            return $app->getContainer()?->has($id) ?? false;
        }

        return false;
    }

    /**
     * Prompt user with the given question.
     *
     * @param string $question
     * @param bool|float|int|string|null $default
     * @return bool|float|int|string|null
     */
    public function ask(string $question, bool|float|int|null|string $default = null): bool|float|int|null|string
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        return $helper->ask($this->input, $this->output, new Question($question, $default));
    }

    /**
     * Prompt user with the given question, but the user's input will not be visible.
     *
     * @param string $question
     * @param bool|float|int|string|null $default
     * @return bool|float|int|string|null
     */
    public function secret(string $question, bool|float|int|null|string $default = null): bool|float|int|null|string
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        return $helper->ask($this->input, $this->output, (new Question($question, $default))->setHidden(true));
    }

    /**
     * Prompt for confirmation.
     *
     * @param string $question
     * @param bool $default
     * @return bool
     */
    public function confirm(string $question, bool $default = false): bool
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        return $helper->ask($this->input, $this->output, new ConfirmationQuestion($question, $default));
    }

    /**
     * Prompt multiple choice question.
     *
     * @param string $question
     * @param array $choices
     * @param mixed|null $defaultIndex
     * @param bool $allowMultipleSelections
     * @param int|null $maxAttempt Max attempts on invalid input. Null means unlimited.
     * @param string $prompt
     * @param string $errorMessage
     * @return string|array
     * @throws InvalidArgumentException If $maxAttempt is less than 1.
     */
    public function choice(
        string $question,
        array $choices,
        mixed $defaultIndex = null,
        bool $allowMultipleSelections = false,
        ?int $maxAttempt = null,
        string $prompt = ' > ',
        string $errorMessage = 'Invalid value: "%s"',
    ): string|array {

        $question = (new ChoiceQuestion($question, $choices, $defaultIndex))
            ->setMultiselect($allowMultipleSelections)
            ->setPrompt($prompt)
            ->setErrorMessage($errorMessage)
            ->setMaxAttempts($maxAttempt)
        ;

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Get current date time
     *
     * @param string $format Date time format. Default: Y-m-d H:i:s
     * @return string
     */
    public function getCurrentDatetime(string $format = self::MESSAGE_DATETIME_FORMAT): string
    {
        return date($format);
    }

    /**
     * Get lazy command of this command.
     *
     * @return LazyCommand
     */
    public static function getLazyCommand(): LazyCommand
    {
        return new LazyCommand(
            static::$name,
            [],
            static::$description,
            false,
            static fn(): Command => new static(),
        );
    }

    /**
     * Call another console command.
     *
     * @throws Throwable
     */
    public function call(string $commandName, array $input = []): int
    {
        return $this->getApplication()->doRun(
            new ArrayInput(array_merge(['command' => $commandName], $input)),
            $this->output
        );
    }

    /**
     * Call another console command without output.
     *
     * @throws Throwable
     */
    public function callSilently(string $commandName, array $input = []): int
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        return $this->getApplication()->doRun(
            new ArrayInput(array_merge(['command' => $commandName], $input)),
            $this->output
        );
    }

}
