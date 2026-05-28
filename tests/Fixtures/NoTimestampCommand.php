<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

class NoTimestampCommand extends Command
{
    static string $name = 'test:no-timestamp';
    static string $description = 'Command without timestamps';
    protected bool $messageTimeStamp = false;

    protected function handle(): void
    {
        $this->info('Info message');
        $this->comment('Comment message');
        $this->question('Question message');
        $this->error('Error message');
        $this->line('Line message');
        $this->errorBlock('Section', 'Block message');
        $this->errorBlock('Section', 'Block message', true);
    }
}
