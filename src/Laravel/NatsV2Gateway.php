<?php

declare(strict_types=1);

namespace LaravelNats\Laravel;

use Basis\Nats\Client;
use Basis\Nats\Message\Msg;
use Basis\Nats\Stream\Stream;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\JetStream\BasisJetStreamManager;
use LaravelNats\JetStream\BasisJetStreamPublisher;
use LaravelNats\JetStream\BasisStreamProvisioner;
use LaravelNats\JetStream\PullConsumerBatch;
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
        private readonly BasisJetStreamPublisher $jetStreamPublisher,
        private readonly PullConsumerBatch $pullConsumerBatch,
        private readonly BasisStreamProvisioner $streamProvisioner,
        private readonly Repository $config,
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

    public function ping(?string $connection = null): bool
    {
        return $this->connections->connection($connection)->ping();
    }

    public function disconnect(?string $name = null): void
    {
        $this->connections->disconnect($name);
    }

    public function disconnectAll(): void
    {
        $this->connections->disconnectAll();
    }

    public function jetstream(?string $connection = null): BasisJetStreamManager
    {
        return new BasisJetStreamManager($this->connections, $connection);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers Reserved for future JetStream header support in basis publish path
     */
    public function jetStreamPublish(
        string $stream,
        string $subject,
        array $payload,
        bool $useEnvelope = true,
        bool $waitForAck = true,
        array $headers = [],
        ?string $connection = null,
    ): void {
        $this->jetStreamPublisher->publish(
            $stream,
            $subject,
            $payload,
            $useEnvelope,
            $waitForAck,
            $headers,
            $connection,
        );
    }

    /**
     * @return list<Msg>
     */
    public function jetStreamPull(
        string $stream,
        string $consumer,
        ?int $batch = null,
        ?float $expiresSeconds = null,
        ?string $connection = null,
    ): array {
        $batch ??= (int) $this->config->get('nats_basis.jetstream.pull.default_batch', 10);
        $expiresSeconds ??= (float) $this->config->get('nats_basis.jetstream.pull.default_expires', 0.5);

        return $this->pullConsumerBatch->fetch(
            $stream,
            $consumer,
            $batch,
            $expiresSeconds,
            $connection,
        );
    }

    public function jetStreamProvisionPreset(
        string $presetKey,
        bool $createIfNotExists = true,
        ?string $connection = null,
    ): Stream {
        if ($presetKey === '') {
            throw new InvalidArgumentException('JetStream preset key must be non-empty.');
        }

        return $this->streamProvisioner->provision($presetKey, $createIfNotExists, $connection);
    }
}
