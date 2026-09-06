<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Tests\Fixtures\ChoiceCommand;
use Tests\Fixtures\LockableCommand;
use Tests\Fixtures\LockableExceptionCommand;

/**
 * Regression tests for command lifecycle bugs:
 *  - choice() permanently disabling input interactivity
 *  - locks leaking when handle() throws
 *  - $lockable blocking indefinitely instead of skipping
 *  - Application::run() reporting success after an unrecoverable error
 */
class CommandLifecycleTest extends TestCase
{
    /** @var resource[] Streams opened for interactive input. */
    private array $streams = [];

    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $this->streams = [];
    }

    /**
     * Build an interactive input backed by the given keystrokes.
     */
    private function interactiveInput(array $parameters, string $keystrokes): ArrayInput
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $keystrokes);
        rewind($stream);
        $this->streams[] = $stream;

        $input = new ArrayInput($parameters);
        $input->setStream($stream);
        $input->setInteractive(true);

        return $input;
    }

    private function makeApp(Command $command): Application
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        return $app;
    }

    /**
     * Read the private $lock property that LockableTrait maintains.
     */
    private function heldLock(Command $command): ?Lock
    {
        $property = new ReflectionProperty(Command::class, 'lock');

        /** @var Lock|null $lock */
        $lock = $property->getValue($command);

        return $lock;
    }

    private function lockFor(string $name): Lock
    {
        $store = SemaphoreStore::isSupported() ? new SemaphoreStore() : new FlockStore();

        return (new LockFactory($store))->createLock($name);
    }

    // --- choice() must not disable subsequent prompts ---

    public function testChoiceKeepsInputInteractiveForLaterPrompts(): void
    {
        $app = $this->makeApp(new ChoiceCommand());
        $input = $this->interactiveInput(['command' => 'test:choice'], "alpha\nsecond answer\n");
        $output = new BufferedOutput();

        $app->doRun($input, $output);
        $result = $output->fetch();

        $this->assertStringContainsString('choice:alpha', $result);
        $this->assertStringContainsString('interactive:yes', $result);
        // The follow-up question must actually be asked, not silently defaulted.
        $this->assertStringContainsString('answer:second answer', $result);
        $this->assertStringNotContainsString('answer:fallback', $result);
    }

    public function testChoiceLeavesInputInteractiveFlagUntouched(): void
    {
        $app = $this->makeApp(new ChoiceCommand());
        $input = $this->interactiveInput(['command' => 'test:choice'], "alpha\nsecond answer\n");

        $app->doRun($input, new BufferedOutput());

        $this->assertTrue($input->isInteractive());
    }

    public function testChoiceRejectsNonPositiveMaxAttempts(): void
    {
        $command = new class extends Command {
            static string $name = 'test:choice-invalid-attempts';

            protected function handle(): void
            {
                $this->choice('Pick one', ['alpha', 'beta'], null, false, 0);
            }
        };

        $app = $this->makeApp($command);
        $input = $this->interactiveInput(['command' => 'test:choice-invalid-attempts'], "alpha\n");
        $output = new BufferedOutput();

        $status = $app->doRun($input, $output);

        // Surfaced through Command::execute()'s error handling rather than a raw TypeError.
        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('positive value', $output->fetch());
    }

    public function testChoiceRetriesInvalidInputUpToMaxAttempts(): void
    {
        $command = new class extends Command {
            static string $name = 'test:choice-retry';
            protected bool $messageTimeStamp = false;

            protected function handle(): void
            {
                $this->line('picked:' . $this->choice('Pick one', ['alpha', 'beta'], null, false, 2));
            }
        };

        $app = $this->makeApp($command);
        // First answer is invalid, second is accepted — within the 2 attempt budget.
        $input = $this->interactiveInput(['command' => 'test:choice-retry'], "nonsense\nbeta\n");
        $output = new BufferedOutput();

        $status = $app->doRun($input, $output);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('picked:beta', $output->fetch());
    }

    public function testChoiceFailsAfterExhaustingMaxAttempts(): void
    {
        $command = new class extends Command {
            static string $name = 'test:choice-exhausted';

            protected function handle(): void
            {
                $this->choice('Pick one', ['alpha', 'beta'], null, false, 1);
            }
        };

        $app = $this->makeApp($command);
        $input = $this->interactiveInput(['command' => 'test:choice-exhausted'], "nonsense\nbeta\n");
        $output = new BufferedOutput();

        $status = $app->doRun($input, $output);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('nonsense', $output->fetch());
    }

    // --- locks must be released even when handle() throws ---

    public function testLockIsReleasedWhenHandleThrows(): void
    {
        $command = new LockableExceptionCommand();
        $app = $this->makeApp($command);
        $output = new BufferedOutput();

        $status = $app->doRun(new ArrayInput(['command' => 'test:lockable-exception']), $output);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Lockable command failed', $output->fetch());
        $this->assertNull($this->heldLock($command), 'Lock should be released after an exception.');
    }

    public function testLockIsReleasedAfterSuccessfulRun(): void
    {
        $command = new LockableCommand();
        $app = $this->makeApp($command);

        $status = $app->doRun(new ArrayInput(['command' => 'test:lockable']), new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertNull($this->heldLock($command), 'Lock should be released after a successful run.');
    }

    public function testLockReleasedByOneRunCanBeReacquired(): void
    {
        $app = $this->makeApp(new LockableExceptionCommand());
        $app->doRun(new ArrayInput(['command' => 'test:lockable-exception']), new BufferedOutput());

        $lock = $this->lockFor('test:lockable-exception');
        $this->assertTrue($lock->acquire(), 'A leaked lock would block re-acquisition.');
        $lock->release();
    }

    // --- $lockable must skip, not block, when the lock is held ---

    public function testLockableCommandSkipsWhenLockIsHeldElsewhere(): void
    {
        $lock = $this->lockFor('test:lockable');
        $this->assertTrue($lock->acquire(), 'Precondition: the external lock must be acquired.');

        try {
            $app = $this->makeApp(new LockableCommand());
            $output = new BufferedOutput();

            // Before the fix this blocked forever waiting on the lock.
            $status = $app->doRun(new ArrayInput(['command' => 'test:lockable']), $output);
            $result = $output->fetch();

            $this->assertSame(Command::SUCCESS, $status);
            $this->assertStringContainsString('already running', $result);
            $this->assertStringNotContainsString('Lockable command executed', $result);
        } finally {
            $lock->release();
        }
    }

    public function testLockableCommandRunsOnceLockIsFree(): void
    {
        $lock = $this->lockFor('test:lockable');
        $lock->acquire();
        $lock->release();

        $app = $this->makeApp(new LockableCommand());
        $output = new BufferedOutput();

        $app->doRun(new ArrayInput(['command' => 'test:lockable']), $output);

        $this->assertStringContainsString('Lockable command executed', $output->fetch());
    }

    // --- Application::run() must report failure, not success ---

    public function testRunReturnsFailureWhenExceptionsAreNotCaught(): void
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->setCatchExceptions(false);

        $status = $app->run(new ArrayInput(['command' => 'does-not-exist']), new BufferedOutput());

        $this->assertSame(Command::FAILURE, $status);
        $this->assertNotSame(Command::SUCCESS, $status);
    }

    public function testRunReturnsSuccessForAValidCommand(): void
    {
        $app = $this->makeApp(new LockableCommand());
        $app->setCatchExceptions(false);

        $status = $app->run(new ArrayInput(['command' => 'test:lockable']), new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $status);
    }
}
