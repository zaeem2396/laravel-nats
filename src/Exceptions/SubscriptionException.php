<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

/**
 * SubscriptionException is thrown when subscription operations fail.
 *
 * This includes:
 * - Invalid subject patterns
 * - Subscription limit exceeded
 * - Failed unsubscribe
 */
class SubscriptionException extends NatsException
{
    /**
     * Create an exception for invalid subject.
     */
    public static function invalidSubject(string $subject, string $reason = ''): self
    {
        $message = sprintf('Invalid subject: "%s"', $subject);
        if ($reason !== '') {
            $message .= ' - ' . $reason;
        }

        return new self($message);
    }

    /**
     * Create an exception for subscription not found.
     */
    public static function notFound(string $sid): self
    {
        return new self(sprintf('Subscription not found: %s', $sid));
    }

    /**
     * Create an exception for subscription limit.
     */
    public static function limitExceeded(int $limit): self
    {
        return new self(sprintf('Subscription limit exceeded: maximum %d subscriptions', $limit));
    }
}
