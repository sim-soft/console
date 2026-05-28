<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

class LockableCommand extends Command
{
    static string $name = 'test:lockable';
    static string $description = 'A lockable test command';
    protected bool $lockable = true;

    protected function handle(): void
    {
        $this->info('Lockable command executed');
    }
}
