<?php

namespace Simsoft\Console\Traits;

use RuntimeException;

/**
 * File directory trait.
 */
trait FileDirectory
{
    /**
     * Create directory if not exists
     * @param string $path
     * @param int $mode
     * @param string $errorMessage
     * @return void
     */
    protected function mkdir(string $path, int $mode = 0777, string $errorMessage = 'Failed to create directory: {path}'): void
    {
        if (!file_exists($path) && !mkdir($path, $mode, true)) {
            throw new RuntimeException(strtr($errorMessage, ['{path}' => $path]));
        }
    }
}
