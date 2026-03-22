<?php

declare(strict_types=1);

namespace LaravelNats\Laravel;

use Basis\Nats\Client;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;
use LaravelNats\Subscriber\Contracts\NatsSubscriberContract;
use LaravelNats\Subscriber\InboundMessage;

/**
 * Facade root for the v2 NATS stack (basis-company/nats envelope publisher + subscriber).
 *
 * @see \LaravelNats\Laravel\Facades\NatsV2
 */
final class NatsV2Gateway
{
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly NatsPublisherContract $publisher,
        private readonly NatsSubscriberContract $subscriber,
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

    /**
     * @param callable(InboundMessage): void $handler
     */
    public function subscribe(string $subject, callable $handler, ?string $queueGroup = null, ?string $connection = null): string
    {
        return $this->subscriber->subscribe($subject, $handler, $queueGroup, $connection);
    }

    public function unsubscribe(string $subscriptionId): void
    {
        $this->subscriber->unsubscribe($subscriptionId);
    }

    public function unsubscribeAll(?string $connection = null): void
    {
        $this->subscriber->unsubscribeAll($connection);
    }

    public function process(?string $connection = null, int|float|null $timeout = 0): mixed
    {
        return $this->subscriber->process($connection, $timeout);
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
