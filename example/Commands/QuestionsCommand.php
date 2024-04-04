<?php

namespace Example\Commands;

use Simsoft\Console\Command;

class QuestionsCommand extends Command
{
    static string $name = 'example:questions';
    static string $description = 'Let me know you better.';

    //protected bool $lockable = true; // Enable lock. Default: false.

    //protected bool $messageTimeStamp = false;

    /**
     * @throws \Throwable
     */
    protected function handle(): void
    {
        $name = $this->ask('What is your name?', 'John');
        $this->info("Your name is \"$name\"");

        $secret = $this->secret('Please tell me a secret?');
        $this->info("Your secret is \"$secret\"");

        if ($this->confirm('Are you above 18yo (y/n)?')) {
            $this->info('You have grow up!');
        } else {
            $this->info("You are very young!");
        }

        $myChoice = $this->choice('Which color do you like?', ['Yellow', 'Orange', 'Blue']);
        $this->info("You have selected \"$myChoice\"");
    }
}
