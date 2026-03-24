<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Exceptions;

use InvalidArgumentException;

final class SubscriptionNotFoundException extends InvalidArgumentException
{
    public static function forId(string $id): self
    {
        return new self("No subscription found for id [{$id}].");
    }
}
