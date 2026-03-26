<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue\Contracts;

/**
 * Minimal surface required by {@see \LaravelNats\Laravel\Queue\NatsJob} for retries, DLQ, and release.
 */
interface NatsJobQueueBridge
{
    /**
     * @param string|null $queue
     * @param array<string, mixed> $options
     */
    public function pushRaw(string $payload, $queue = null, array $options = []): ?string;

    /**
     * @param \DateInterval|\DateTimeInterface|int $delay
     * @param object|string $job
     * @param mixed $data
     * @param string|null $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null): mixed;

    public function getDeadLetterQueueSubject(): ?string;

    public function getRetryAfter(): int;

    public function getMaxTries(): int;

    public function publishRawToSubject(string $subject, string $payload): void;

    /**
     * Called when a popped job is finished (deleted, released, or failed) so drivers can decrement in-flight counters.
     */
    public function notifyJobHandled(): void;
}
