<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;
use Simsoft\Console\Traits\FileOption;

class FileOptionCommand extends Command
{
    use FileOption;

    static string $name = 'test:file-option';
    static string $description = 'Command with file option';

    protected function init(): void
    {
        $this->addFileOption();
    }

    protected function handle(): void
    {
        // Single file
        $single = $this->fileOption();
        if ($single !== null) {
            $this->line('SINGLE:' . $single);
        }

        // Multiple files
        $multiple = $this->fileOption('file', null, true);
        if ($multiple !== null) {
            $this->line('MULTI:' . implode('|', $multiple));
        }

        // With extension
        $withExt = $this->fileOption('file', null, false, 'csv');
        if ($withExt !== null) {
            $this->line('EXT:' . $withExt);
        }

        // Multiple with extension
        $multiExt = $this->fileOption('file', null, true, 'xlsx');
        if ($multiExt !== null) {
            $this->line('MULTI_EXT:' . implode('|', $multiExt));
        }

        if ($single === null) {
            $this->line('NULL');
        }
    }
}
