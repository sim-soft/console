<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use RuntimeException;
use Simsoft\Console\Command;

class LockableExceptionCommand extends Command
{
    static string $name = 'test:lockable-exception';
    static string $description = 'A lockable command that throws';
    protected bool $lockable = true;

    protected function handle(): void
    {
        throw new RuntimeException('Lockable command failed');
    }
}
