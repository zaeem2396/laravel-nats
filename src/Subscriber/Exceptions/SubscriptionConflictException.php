<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Exceptions;

use LogicException;

final class SubscriptionConflictException extends LogicException
{
    public static function duplicate(string $subject, ?string $queueGroup, string $connection): self
    {
        $qg = $queueGroup !== null ? "queue group \"{$queueGroup}\"" : 'no queue group';

        return new self("A subscription for subject \"{$subject}\" ({$qg}) on connection \"{$connection}\" already exists.");
    }
}
