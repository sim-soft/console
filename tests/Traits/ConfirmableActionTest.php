<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\ConfirmableAction;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ConfirmableActionTest extends TestCase
{
    public function testForceOptionBypassesConfirmation(): void
    {
        $command = new class extends Command {
            use ConfirmableAction;

            static string $name = 'test:confirm';
            static string $description = 'Confirm test';

            protected function init(): void
            {
                $this->addForceOption();
            }

            protected function handle(): void
            {
                if (!$this->confirmToProceed('This is dangerous!', 'production')) {
                    return;
                }
                $this->line('EXECUTED');
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:confirm', '--force' => true]);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('EXECUTED', $output->fetch());
    }

    public function testNonProductionEnvProceedsWithoutConfirmation(): void
    {
        $command = new class extends Command {
            use ConfirmableAction;

            static string $name = 'test:confirm-dev';
            static string $description = 'Confirm dev test';

            protected function init(): void
            {
                $this->addForceOption();
            }

            protected function handle(): void
            {
                if (!$this->confirmToProceed('Dangerous!', 'development')) {
                    return;
                }
                $this->line('EXECUTED');
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $input = new ArrayInput(['command' => 'test:confirm-dev']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $this->assertStringContainsString('EXECUTED', $output->fetch());
    }

    public function testProductionWithoutForceAndNonInteractiveCancels(): void
    {
        $command = new class extends Command {
            use ConfirmableAction;

            static string $name = 'test:confirm-prod';
            static string $description = 'Confirm prod test';

            protected function init(): void
            {
                $this->addForceOption();
            }

            protected function handle(): void
            {
                if (!$this->confirmToProceed('Dangerous!', 'production')) {
                    return;
                }
                $this->line('EXECUTED');
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        // Non-interactive input (no --force, no TTY) — confirm returns false
        $input = new ArrayInput(['command' => 'test:confirm-prod']);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $app->doRun($input, $output);

        $result = $output->fetch();
        $this->assertStringNotContainsString('EXECUTED', $result);
        $this->assertStringContainsString('Command cancelled', $result);
    }
}
