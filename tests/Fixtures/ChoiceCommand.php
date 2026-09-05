<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

class ChoiceCommand extends Command
{
    static string $name = 'test:choice';
    static string $description = 'A command that asks a choice then a follow-up question';
    protected bool $messageTimeStamp = false;

    protected function handle(): void
    {
        $choice = $this->choice('Pick one', ['alpha', 'beta'], 0);
        $this->line("choice:$choice");

        $this->line('interactive:' . ($this->input->isInteractive() ? 'yes' : 'no'));

        $answer = $this->ask('Follow up', 'fallback');
        $this->line("answer:$answer");
    }
}
