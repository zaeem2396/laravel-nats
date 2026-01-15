<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

/**
 * ProtocolException is thrown when NATS protocol errors occur.
 *
 * This includes:
 * - Invalid protocol messages
 * - Server error responses (-ERR)
 * - Malformed messages
 * - Unknown protocol commands
 */
class ProtocolException extends NatsException
{
    /**
     * Create an exception for server errors.
     *
     * The NATS server sends -ERR messages for various protocol errors.
     */
    public static function serverError(string $error): self
    {
        return new self('Server error: ' . $error);
    }

    /**
     * Create an exception for invalid messages.
     */
    public static function invalidMessage(string $reason): self
    {
        return new self('Invalid protocol message: ' . $reason);
    }

    /**
     * Create an exception for unknown commands.
     */
    public static function unknownCommand(string $command): self
    {
        return new self('Unknown protocol command: ' . $command);
    }

    /**
     * Create an exception for parse errors.
     */
    public static function parseFailed(string $data, string $reason = ''): self
    {
        $message = sprintf('Failed to parse protocol data: %s', substr($data, 0, 100));
        if ($reason !== '') {
            $message .= ' - ' . $reason;
        }

        return new self($message);
    }
}
