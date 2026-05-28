<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

class CallerCommand extends Command
{
    static string $name = 'test:caller';
    static string $description = 'Command that calls other commands';

    protected function handle(): void
    {
        $this->call('test:args', ['name' => 'World']);
        $this->callSilently('test:args', ['name' => 'Silent']);
    }
}
