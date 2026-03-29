<?php

declare(strict_types=1);

namespace LaravelNats\Security\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when `nats_basis.acl` rejects a publish or subscribe subject.
 */
final class SubjectNotAllowedException extends InvalidArgumentException
{
    public static function publish(string $subject): self
    {
        return new self(sprintf('ACL: publish to subject "%s" is not allowed.', $subject));
    }

    public static function subscribe(string $subject): self
    {
        return new self(sprintf('ACL: subscribe to subject "%s" is not allowed.', $subject));
    }
}
