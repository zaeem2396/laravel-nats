<?php

declare(strict_types=1);

namespace LaravelNats\Outbox;

use LaravelNats\Support\NatsHeaderBag;

/**
 * Storage-agnostic message DTO for a user-managed transactional outbox table.
 */
final class NatsOutboxMessage
{
    /**
     * @var array<string, mixed>
     */
    public readonly array $headers;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|NatsHeaderBag $headers
     */
    public function __construct(
        public readonly string $id,
        public readonly string $subject,
        public readonly array $payload,
        array|NatsHeaderBag $headers = [],
        public readonly ?string $connection = null,
    ) {
        $this->headers = $headers instanceof NatsHeaderBag ? $headers->toArray() : $headers;
    }
}
