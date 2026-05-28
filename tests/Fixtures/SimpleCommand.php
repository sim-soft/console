<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

class SimpleCommand extends Command
{
    static string $name = 'test:simple';
    static string $description = 'A simple test command';

    protected function handle(): void
    {
        $this->info('Simple command executed');
    }
}
