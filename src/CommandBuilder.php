<?php

namespace Simsoft\Console;

use Closure;

/**
 * CommandBuilder class.
 */
class CommandBuilder
{
    protected string $description = '';

    protected ?Closure $inputCallback = null;

    /**
     * Constructor.
     */
    public function __construct(protected string $name, protected Closure $callback)
    {

    }

    /**
     * Set command description.
     *
     * @param string $description
     * @return $this
     */
    public function purpose(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set command input.
     *
     * @param Closure $callback
     * @return $this
     */
    public function input(Closure $callback): static
    {
        $this->inputCallback = $callback;
        return $this;
    }

    /**
     * Build Closure command.
     *
     * @return ClosureCommand
     */
    public function build(): ClosureCommand
    {
        $command = new ClosureCommand($this->name, $this->inputCallback);
        $command::$name = $this->name;
        $command::$description = $this->description;
        $command->setDescription($this->description);
        $command::$callback = $this->callback;
        return $command;
    }
}
