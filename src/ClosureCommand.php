<?php

namespace Simsoft\Console;

use Closure;

/**
 * ClosureCommand
 */
class ClosureCommand extends Command
{

    /** @var Closure|null Console input closure */
    public static ?Closure $inputCallback = null;

    /** @var Closure|null A closure. */
    static ?Closure $callback = null;

    /**
     * Constructor.
     *
     * @param string|null $name
     * @param Closure|null $inputCallback
     */
    public function __construct(?string $name = null, ?Closure $inputCallback = null)
    {
        static::$inputCallback = $inputCallback;

        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function init(): void
    {
        if ($callback = static::$inputCallback?->bindTo($this)){
            $callback();
        }
    }

    /**
     * @inheritdoc
     */
    protected function handle(): void
    {
        if ($callback = static::$callback?->bindTo($this)){
            $callback();
        }
    }

}
