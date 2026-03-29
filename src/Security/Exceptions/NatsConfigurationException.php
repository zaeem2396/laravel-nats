<?php

declare(strict_types=1);

namespace LaravelNats\Security\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when `nats_basis` fails optional boot-time validation.
 */
final class NatsConfigurationException extends InvalidArgumentException
{
    public static function forConnection(string $name, string $message): self
    {
        return new self(sprintf('NATS basis connection [%s]: %s', $name, $message));
    }

    public static function global(string $message): self
    {
        return new self($message);
    }
}
