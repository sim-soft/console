<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\RetryableTask;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RetryableTaskTest extends TestCase
{
    private function createRetryCommand(int $failTimes = 0): Command
    {
        return new class($failTimes) extends Command {
            use RetryableTask;

            static string $name = 'test:retry';
            static string $description = 'Retry test';
            private static int $failCount = 0;
            private static int $attempts = 0;

            public function __construct(int $failTimes = 0)
            {
                static::$failCount = $failTimes;
                static::$attempts = 0;
                parent::__construct();
            }

            protected function handle(): void
            {
                $result = $this->retry(function (int $attempt) {
                    static::$attempts = $attempt;
                    if ($attempt <= static::$failCount) {
                        throw new RuntimeException("Fail #$attempt");
                    }
                    return 'SUCCESS';
                }, maxAttempts: 5, delayMs: 0);

                $this->line("RESULT:$result");
                $this->line('ATTEMPTS:' . static::$attempts);
            }
        };
    }

    private function runCommand(Command $command): string
    {
        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:retry']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        return $output->fetch();
    }

    public function testSucceedsOnFirstAttempt(): void
    {
        $output = $this->runCommand($this->createRetryCommand(0));
        $this->assertStringContainsString('RESULT:SUCCESS', $output);
        $this->assertStringContainsString('ATTEMPTS:1', $output);
    }

    public function testRetriesAndSucceeds(): void
    {
        $output = $this->runCommand($this->createRetryCommand(2));
        $this->assertStringContainsString('RESULT:SUCCESS', $output);
        $this->assertStringContainsString('ATTEMPTS:3', $output);
    }

    public function testRetriesOutputsComments(): void
    {
        $output = $this->runCommand($this->createRetryCommand(1));
        $this->assertStringContainsString('Attempt 1 failed', $output);
        $this->assertStringContainsString('Retrying', $output);
    }

    public function testThrowsAfterMaxAttempts(): void
    {
        $command = new class extends Command {
            use RetryableTask;

            static string $name = 'test:retry-fail';
            static string $description = 'Retry fail test';

            protected function handle(): void
            {
                $this->retry(function () {
                    throw new RuntimeException('Always fails');
                }, maxAttempts: 3, delayMs: 0);
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:retry-fail']);
        $output = new BufferedOutput();
        $status = $app->doRun($input, $output);

        // Command should fail (exception caught by Command::execute)
        $this->assertSame(1, $status);
        $this->assertStringContainsString('Always fails', $output->fetch());
    }

    public function testCustomOnRetryCallback(): void
    {
        $retries = [];

        $command = new class($retries) extends Command {
            use RetryableTask;

            static string $name = 'test:retry-custom';
            static string $description = 'Custom retry';
            private static array $retries = [];

            public function __construct(array &$retries)
            {
                static::$retries = &$retries;
                parent::__construct();
            }

            protected function handle(): void
            {
                $attempt = 0;
                $this->retry(
                    function () use (&$attempt) {
                        $attempt++;
                        if ($attempt < 3) {
                            throw new RuntimeException("Fail");
                        }
                        return true;
                    },
                    maxAttempts: 3,
                    delayMs: 0,
                    onRetry: function (int $a, \Throwable $e) {
                        static::$retries[] = $a;
                    }
                );

                $this->line('RETRIES:' . count(static::$retries));
            }
        };

        $output = $this->runCommand($command);
        $this->assertStringContainsString('RETRIES:2', $output);
    }
}
