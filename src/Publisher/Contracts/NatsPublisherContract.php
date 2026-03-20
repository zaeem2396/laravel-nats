<?php

declare(strict_types=1);

namespace LaravelNats\Publisher\Contracts;

/**
 * Contract for publishing messages through the v2 NATS stack (basis-company/nats).
 */
interface NatsPublisherContract
{
    /**
     * Publish a JSON envelope to the given subject. Headers are sent as NATS message headers (HPUB).
     *
     * @param array<string, mixed> $payload Becomes the envelope "data" field
     * @param array<string, string> $headers NATS headers (values coerced to string)
     */
    public function publish(string $subject, array $payload, array $headers = [], ?string $connection = null): void;
}
