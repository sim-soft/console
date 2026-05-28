<?php

namespace Simsoft\Console\Traits;

use Throwable;

/**
 * Trait RetryableTask
 *
 * Provides retry logic with configurable attempts, delay, and backoff.
 *
 * @method error(string $message): void
 * @method comment(string $message): void
 */
trait RetryableTask
{
    /**
     * Retry a callback up to N times with optional delay between attempts.
     *
     * @param callable $callback The operation to attempt.
     * @param int $maxAttempts Maximum number of attempts.
     * @param int $delayMs Delay between retries in milliseconds.
     * @param bool $exponentialBackoff Double the delay after each failure.
     * @param callable|null $onRetry Called on each retry with (attempt, exception).
     * @return mixed The callback result on success.
     * @throws Throwable The last exception if all attempts fail.
     */
    protected function retry(
        callable  $callback,
        int       $maxAttempts = 3,
        int       $delayMs = 1000,
        bool      $exponentialBackoff = false,
        ?callable $onRetry = null,
    ): mixed
    {
        $attempts = 0;
        $currentDelay = $delayMs;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                return $callback($attempts);
            } catch (Throwable $e) {
                $lastException = $e;

                if ($attempts >= $maxAttempts) {
                    break;
                }

                if ($onRetry) {
                    $onRetry($attempts, $e);
                }

                if (!$onRetry) {
                    $this->comment("Attempt $attempts failed: {$e->getMessage()}. Retrying...");
                }

                if ($currentDelay > 0) {
                    usleep($currentDelay * 1000);
                }

                if ($exponentialBackoff) {
                    $currentDelay *= 2;
                }
            }
        }

        throw $lastException;
    }
}
