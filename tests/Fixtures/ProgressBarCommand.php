<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

class ProgressBarCommand extends Command
{
    static string $name = 'test:progress';
    static string $description = 'Command with progress bar';

    protected function handle(): void
    {
        $items = range(1, 5);
        $results = [];

        $this->withProgressBar($items, function ($item) use (&$results) {
            $results[] = $item;
        });

        $this->newLine();
        $this->line('DONE:' . implode(',', $results));
    }
}
