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

    // --- A renamed option must still be found ---

    /**
     * Run a command whose dry-run flag is registered as --simulate.
     *
     * @param array<string, mixed> $input
     */
    private function runRenamed(array $input = []): string
    {
        $command = new class extends Command {
            use DryRunOption;

            static string $name = 'test:dryrun-renamed';
            static string $description = 'Renamed dry run test';
            public static bool $actionExecuted = false;

            protected function init(): void
            {
                $this->addDryRunOption(name: 'simulate');
            }

            protected function handle(): void
            {
                $this->unlessDryRun('Delete all records', function () {
                    static::$actionExecuted = true;
                    $this->line('DELETED');
                });

                $this->line('DRYRUN:' . ($this->isDryRun() ? 'yes' : 'no'));
            }
        };

        $command::$actionExecuted = false;

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $app->doRun(
            new ArrayInput(array_merge(['command' => 'test:dryrun-renamed'], $input)),
            $output
        );

        return $output->fetch();
    }

    public function testRenamedOptionRunsTheActionWhenNotSet(): void
    {
        // isDryRun() used to look for 'dry-run' regardless of what was
        // registered, so this threw "The "dry-run" option does not exist"
        // before the action was ever reached.
        $output = $this->runRenamed();

        $this->assertStringContainsString('DELETED', $output);
        $this->assertStringContainsString('DRYRUN:no', $output);
        $this->assertStringNotContainsString('does not exist', $output);
    }

    public function testRenamedOptionSuppressesTheActionWhenSet(): void
    {
        $output = $this->runRenamed(['--simulate' => true]);

        $this->assertStringNotContainsString('DELETED', $output);
        $this->assertStringContainsString('[DRY RUN] Delete all records', $output);
        $this->assertStringContainsString('DRYRUN:yes', $output);
    }

    public function testExplicitNameStillOverridesTheRegisteredOne(): void
    {
        // The parameter remains, so a command registering more than one flag
        // can still ask for a specific one.
        $command = new class extends Command {
            use DryRunOption;

            static string $name = 'test:dryrun-explicit';
            static string $description = 'Explicit name test';

            protected function init(): void
            {
                $this->addDryRunOption();
                $this->addDryRunOption(name: 'simulate');
            }

            protected function handle(): void
            {
                $this->line('DEFAULT:' . ($this->isDryRun('dry-run') ? 'yes' : 'no'));
                $this->line('RENAMED:' . ($this->isDryRun('simulate') ? 'yes' : 'no'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $app->doRun(
            new ArrayInput(['command' => 'test:dryrun-explicit', '--dry-run' => true]),
            $output
        );

        $result = $output->fetch();
        $this->assertStringContainsString('DEFAULT:yes', $result);
        $this->assertStringContainsString('RENAMED:no', $result);
    }

    public function testUnregisteredOptionExplainsItself(): void
    {
        // Symfony named the missing option but not the reason the trait went
        // looking for it.
        $command = new class extends Command {
            use DryRunOption;

            static string $name = 'test:dryrun-unregistered';
            static string $description = 'Unregistered test';

            protected function handle(): void
            {
                $this->unlessDryRun('Delete all records', fn() => $this->line('DELETED'));
            }
        };

        $app = Application::make('Test', '1.0');
        $app->setAutoExit(false);
        $app->addCommand($command);

        $output = new BufferedOutput();
        $status = $app->doRun(new ArrayInput(['command' => 'test:dryrun-unregistered']), $output);

        $result = $output->fetch();
        $this->assertSame(1, $status);
        $this->assertStringContainsString('addDryRunOption()', $result);
        $this->assertStringNotContainsString('DELETED', $result);
    }
}
