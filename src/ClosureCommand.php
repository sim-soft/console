<?php

namespace Simsoft\Console;

use Closure;

/**
 * ClosureCommand
 */
class ClosureCommand extends Command
{
    /** @var Closure|null Console input closure */
    protected ?Closure $inputClosure = null;

    /** @var Closure|null The command handler closure. */
    protected ?Closure $handlerCallback = null;

    /**
     * Constructor.
     *
     * @param string|null $name
     * @param Closure|null $inputCallback
     */
    public function __construct(?string $name = null, ?Closure $inputCallback = null)
    {
        $this->inputClosure = $inputCallback;

        parent::__construct($name);
    }

    /**
     * Set the handler callback.
     *
     * @param Closure|null $callback
     * @return $this
     */
    public function setHandler(?Closure $callback): static
    {
        $this->handlerCallback = $callback;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function init(): void
    {
        $callback = $this->inputClosure?->bindTo($this);
        if ($callback) {
            $callback();
        }
    }

    /**
     * @inheritdoc
     */
    protected function handle(): void
    {
        $callback = $this->handlerCallback?->bindTo($this);
        if ($callback) {
            $callback();
        }
    }
}
