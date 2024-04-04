<?php

namespace Example\Commands;

use Simsoft\Console\Command;

class HelloWorldCommand extends Command
{
    static string $name = 'example:welcome';
    static string $description = 'Hello World';

    /**
     * @throws \Throwable
     */
    protected function handle(): void
    {
        // Basic output.
        $this->info('Hello World');
        $this->comment('Comment text');
        $this->question('My Question?');
        $this->error('Warning');
        $this->line('Simple line');
        $this->errorBlock('Block Header', 'Block message');
        $this->newLine(3);

        // Call other commands.
        $this->call('example:friendly', [
            'name' => 'Jane',
            '--age' => 18,
        ]);

        // Call other commands.
        $this->callSilently('example:friendly', [
            'name' => 'John',
            '--age' => 28,
        ]);
    }
}
