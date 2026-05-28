<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ArgumentCommand extends Command
{
    static string $name = 'test:args';
    static string $description = 'Command with arguments and options';

    protected function init(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Your name')
            ->addArgument('greeting', InputArgument::OPTIONAL, 'Greeting text', 'Hello')
            ->addOption('shout', 's', InputOption::VALUE_NONE, 'Shout the greeting')
            ->addOption('repeat', 'r', InputOption::VALUE_REQUIRED, 'Repeat count', '1');
    }

    protected function handle(): void
    {
        $name = $this->argument('name');
        $greeting = $this->argument('greeting');
        $shout = $this->option('shout');
        $repeat = (int)$this->option('repeat');

        $message = "$greeting, $name!";
        if ($shout) {
            $message = strtoupper($message);
        }

        for ($i = 0; $i < $repeat; $i++) {
            $this->line($message);
        }
    }
}
