<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use Simsoft\Console\Application;
use Simsoft\Console\Command;
use Simsoft\Console\Traits\DryRunOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DryRunOptionTest extends TestCase
{
    private function createCommand(): Command
    {
        return new class extends Command {
            use DryRunOption;

            static string $name = 'test:dryrun';
            static string $description = 'Dry run test';
            public static bool $actionExecuted = false;

            protected function init(): void
            {
                $this->addDryRunOption();
            }

            protected function handle(): void
            {
                static::$actionExecuted = false;

                $this->unlessDryRun('Delete all records', function () {
                    static::$actionExecuted = true;
                    $this->line('DELETED');
                });

                $this->line('DRYRUN:' . ($this->isDryRun() ? 'yes' : 'no'));
            }
        };
    }

    private function runCommand(array $input = []): string
    {
        $command = $this->createCommand();
        $command::$actionExecuted = false;

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $arrayInput = new ArrayInput(array_merge(['command' => 'test:dryrun'], $input));
        $output = new BufferedOutput();
        $app->doRun($arrayInput, $output);

        return $output->fetch();
    }

    public function testWithoutDryRunExecutesAction(): void
    {
        $output = $this->runCommand();
        $this->assertStringContainsString('DELETED', $output);
        $this->assertStringContainsString('DRYRUN:no', $output);
    }

    public function testWithDryRunSkipsAction(): void
    {
        $output = $this->runCommand(['--dry-run' => true]);
        $this->assertStringNotContainsString('DELETED', $output);
        $this->assertStringContainsString('[DRY RUN] Delete all records', $output);
        $this->assertStringContainsString('DRYRUN:yes', $output);
    }

    public function testIsDryRunReturnsFalseByDefault(): void
    {
        $output = $this->runCommand();
        $this->assertStringContainsString('DRYRUN:no', $output);
    }

    public function testUnlessDryRunReturnsCallbackResult(): void
    {
        $command = new class extends Command {
            use DryRunOption;

            static string $name = 'test:dryrun-return';
            static string $description = 'Return test';

            protected function init(): void
            {
                $this->addDryRunOption();
            }

            protected function handle(): void
            {
                $result = $this->unlessDryRun('Get value', fn() => 42);
                $this->line('RESULT:' . ($result ?? 'null'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        // Without dry-run
        $input = new ArrayInput(['command' => 'test:dryrun-return']);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->assertStringContainsString('RESULT:42', $output->fetch());

        // With dry-run
        $input = new ArrayInput(['command' => 'test:dryrun-return', '--dry-run' => true]);
        $output = new BufferedOutput();
        $app->doRun($input, $output);
        $this->assertStringContainsString('RESULT:null', $output->fetch());
    }
}
