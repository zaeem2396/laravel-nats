<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

/**
 * TimeoutException is thrown when operations exceed their time limit.
 *
 * This is primarily used for:
 * - Request/Reply timeout (no response received)
 * - Connection timeout
 * - Read timeout
 */
class TimeoutException extends NatsException
{
    /**
     * Create an exception for request timeout.
     */
    public static function requestTimeout(string $subject, float $timeout): self
    {
        return new self(
            sprintf('Request to "%s" timed out after %.2f seconds', $subject, $timeout),
        );
    }

    /**
     * Create an exception for read timeout.
     */
    public static function readTimeout(float $timeout): self
    {
        return new self(
            sprintf('Read operation timed out after %.2f seconds', $timeout),
        );
    }
}
