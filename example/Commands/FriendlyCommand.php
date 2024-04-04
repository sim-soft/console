<?php

namespace Example\Commands;

use Simsoft\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class FriendlyCommand extends Command
{
    static string $name = 'example:friendly';
    static string $description = 'Hi, I am a friendly command.';

    protected function init(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED);
        $this->addOption('age');
    }

    protected function handle(): void
    {
        $name = $this->argument('name');
        $age = $this->option('age');

        $this->info("Hello World, I am $name");

        if ($age) {
            $this->info("I am $age years old.");
        }
    }
}
