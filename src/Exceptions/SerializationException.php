<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

/**
 * SerializationException is thrown when serialization/deserialization fails.
 *
 * This includes:
 * - JSON encode/decode errors
 * - Invalid data types
 * - Encoding issues
 */
class SerializationException extends NatsException
{
    /**
     * Create an exception for serialization failure.
     */
    public static function serializeFailed(string $reason): self
    {
        return new self('Failed to serialize data: ' . $reason);
    }

    /**
     * Create an exception for deserialization failure.
     */
    public static function deserializeFailed(string $reason): self
    {
        return new self('Failed to deserialize data: ' . $reason);
    }

    /**
     * Create an exception from JSON error.
     */
    public static function jsonError(int $errorCode): self
    {
        $message = match ($errorCode) {
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error',
            JSON_ERROR_SYNTAX => 'Syntax error',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters',
            default => 'Unknown JSON error',
        };

        return new self('JSON error: ' . $message);
    }
}
