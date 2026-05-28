<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

class TableCommand extends Command
{
    static string $name = 'test:table';
    static string $description = 'Command with table output';

    protected function handle(): void
    {
        $this->table(
            ['Name', 'Score'],
            [
                ['Alice', '100'],
                ['Bob', '95'],
            ]
        );
    }
}
