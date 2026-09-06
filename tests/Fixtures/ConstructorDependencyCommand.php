<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Simsoft\Console\Command;

/**
 * A command with a required constructor argument.
 *
 * Cannot be lazy-loaded: LazyCommand builds it with `new static()`.
 */
class ConstructorDependencyCommand extends Command
{
    static string $name = 'test:constructor-dependency';
    static string $description = 'Requires a constructor argument';

    public function __construct(private readonly string $dependency)
    {
        parent::__construct();
    }

    protected function handle(): void
    {
        $this->info($this->dependency);
    }
}
