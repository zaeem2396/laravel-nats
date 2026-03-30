<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

use RuntimeException;

/**
 * Thrown when a synchronous request receives a NATS 503 "no responders" style reply (Status-Code header).
 */
final class NatsNoRespondersException extends RuntimeException
{
    public function __construct(
        public readonly string $subject,
    ) {
        parent::__construct(sprintf('No responders available for subject "%s" (NATS Status-Code 503).', $subject));
    }
}
