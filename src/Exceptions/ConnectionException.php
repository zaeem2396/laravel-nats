<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

/**
 * ConnectionException is thrown when connection operations fail.
 *
 * This includes:
 * - Failed connection attempts
 * - Socket errors during read/write
 * - Authentication failures
 * - TLS handshake failures
 * - Unexpected disconnections
 */
class ConnectionException extends NatsException
{
    /**
     * Create an exception for connection timeout.
     */
    public static function timeout(string $host, int $port, float $timeout): self
    {
        return new self(
            sprintf('Connection to %s:%d timed out after %.2f seconds', $host, $port, $timeout),
        );
    }

    /**
     * Create an exception for connection refused.
     */
    public static function refused(string $host, int $port): self
    {
        return new self(
            sprintf('Connection to %s:%d refused', $host, $port),
        );
    }

    /**
     * Create an exception for authentication failure.
     */
    public static function authenticationFailed(string $reason = ''): self
    {
        $message = 'Authentication failed';
        if ($reason !== '') {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for not connected state.
     */
    public static function notConnected(): self
    {
        return new self('Not connected to NATS server');
    }

    /**
     * Create an exception for socket write failure.
     */
    public static function writeFailed(string $reason = ''): self
    {
        $message = 'Failed to write to socket';
        if ($reason !== '') {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for socket read failure.
     */
    public static function readFailed(string $reason = ''): self
    {
        $message = 'Failed to read from socket';
        if ($reason !== '') {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for TLS failure.
     */
    public static function tlsFailed(string $reason = ''): self
    {
        $message = 'TLS handshake failed';
        if ($reason !== '') {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for unexpected disconnect.
     */
    public static function disconnected(): self
    {
        return new self('Connection to NATS server was closed unexpectedly');
    }
}
