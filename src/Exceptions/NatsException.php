<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

use Exception;

/**
 * NatsException is the base exception for all NATS-related errors.
 *
 * All package exceptions extend this class, allowing consumers to catch
 * all NATS errors with a single catch block if desired.
 */
class NatsException extends Exception
{
    /**
     * Create a new NATS exception.
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
