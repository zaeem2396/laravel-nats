<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Exceptions;

use InvalidArgumentException;

final class InvalidSubjectException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('NATS subject cannot be empty.');
    }

    public static function tooLong(int $max): self
    {
        return new self("NATS subject exceeds maximum length ({$max}).");
    }
}
