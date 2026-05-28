<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;
use Simsoft\Console\Traits\FileDirectory;

class FileDirectoryCommand extends Command
{
    use FileDirectory;

    static string $name = 'test:file-directory';
    static string $description = 'Command with file directory trait';

    protected function handle(): void
    {
        $path = $this->argument('path');
        $this->mkdir($path);
        $this->line('CREATED:' . $path);
    }

    protected function init(): void
    {
        $this->addArgument('path');
    }
}
