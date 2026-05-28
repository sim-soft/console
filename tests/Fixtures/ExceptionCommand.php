<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use RuntimeException;
use Simsoft\Console\Command;

class ExceptionCommand extends Command
{
    static string $name = 'test:exception';
    static string $description = 'Command that throws an exception';

    protected function handle(): void
    {
        throw new RuntimeException('Something went wrong');
    }
}
