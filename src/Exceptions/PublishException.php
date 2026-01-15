<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

/**
 * PublishException is thrown when publish operations fail.
 *
 * This includes:
 * - Invalid subject
 * - Message too large
 * - Connection issues during publish
 */
class PublishException extends NatsException
{
    /**
     * Create an exception for invalid subject.
     */
    public static function invalidSubject(string $subject, string $reason = ''): self
    {
        $message = sprintf('Cannot publish to invalid subject: "%s"', $subject);
        if ($reason !== '') {
            $message .= ' - ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for message too large.
     */
    public static function messageTooLarge(int $size, int $maxSize): self
    {
        return new self(
            sprintf('Message size %d bytes exceeds maximum allowed size of %d bytes', $size, $maxSize),
        );
    }

    /**
     * Create an exception for publish failure.
     */
    public static function failed(string $subject, string $reason = ''): self
    {
        $message = sprintf('Failed to publish to "%s"', $subject);
        if ($reason !== '') {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }
}
