<?php

declare(strict_types=1);

namespace LaravelNats\Laravel;

use Basis\Nats\Client;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;

/**
 * Facade root for the v2 NATS stack (basis-company/nats + envelope publisher).
 *
 * @see \LaravelNats\Laravel\Facades\NatsV2
 */
final class NatsV2Gateway
{
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly NatsPublisherContract $publisher,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|int|float|bool|null> $headers Values are normalized to strings when publishing
     */
    public function publish(string $subject, array $payload, array $headers = [], ?string $connection = null): void
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if ($value === null) {
                $normalized[$key] = '';

                continue;
            }
            $normalized[$key] = is_string($value) ? $value : (string) $value;
        }

        $this->publisher->publish($subject, $payload, $normalized, $connection);
    }

    public function connection(?string $name = null): Client
    {
        return $this->connections->connection($name);
    }

    public function disconnect(?string $name = null): void
    {
        $this->connections->disconnect($name);
    }

    public function disconnectAll(): void
    {
        $this->connections->disconnectAll();
    }
}
