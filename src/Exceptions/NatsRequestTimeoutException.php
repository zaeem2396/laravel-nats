<?php

declare(strict_types=1);

namespace LaravelNats\Exceptions;

use RuntimeException;

/**
 * Thrown when {@see \LaravelNats\Laravel\NatsV2Gateway::request} exhausts its wait budget.
 */
final class NatsRequestTimeoutException extends RuntimeException
{
    public function __construct(
        public readonly string $subject,
        public readonly float $timeoutSeconds,
    ) {
        parent::__construct(sprintf(
            'Request to "%s" timed out after %.3f second(s).',
            $subject,
            $timeoutSeconds,
        ));
    }
}
