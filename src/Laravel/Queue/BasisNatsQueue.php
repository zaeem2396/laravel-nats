<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Basis\Nats\Client;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Laravel\Queue\Contracts\NatsJobQueueBridge;

/**
 * Laravel queue driver on {@see Client} (basis-company/nats) via {@see ConnectionManager}.
 *
 * Job payloads match the legacy {@see NatsQueue} JSON shape for `queue:work` compatibility.
 *
 * Optional {@see $maxInFlight} limits how many messages this worker process may hold before {@see pop()}
 * returns null (per-process backpressure, not cluster-wide).
 */
class BasisNatsQueue extends Queue implements QueueContract, NatsJobQueueBridge
{
    protected string $defaultQueue;

    protected int $retryAfter;

    protected string $subjectPrefix;

    protected int $maxTries;

    protected ?string $deadLetterQueue = null;

    /**
     * Seconds (fractional) to block in {@see pop()} while draining the socket.
     */
    protected float $popBlockSeconds;

    /**
     * Max jobs popped and not yet completed on this queue instance; used with {@see $maxInFlight}.
     */
    protected int $inFlight = 0;

    /**
     * When set and positive, {@see pop()} returns null while {@see $inFlight} is at this limit.
     */
    protected ?int $maxInFlight = null;

    public function __construct(
        protected ConnectionManager $connections,
        protected ?string $basisConnectionName,
        string $defaultQueue = 'default',
        int $retryAfter = 60,
        int $maxTries = 3,
        ?string $deadLetterQueue = null,
        string $subjectPrefix = 'laravel.queue.',
        float $popBlockSeconds = 0.1,
        ?int $maxInFlight = null,
    ) {
        $this->defaultQueue = $defaultQueue;
        $this->retryAfter = $retryAfter;
        $this->maxTries = $maxTries;
        $this->deadLetterQueue = $deadLetterQueue;
        $this->subjectPrefix = $subjectPrefix;
        $this->popBlockSeconds = $popBlockSeconds > 0 ? $popBlockSeconds : 0.1;
        $this->maxInFlight = $maxInFlight !== null && $maxInFlight > 0 ? $maxInFlight : null;
    }

    public function size($queue = null): int
    {
        return 0;
    }

    public function push($job, $data = '', $queue = null): ?string
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) {
                return $this->pushRaw($payload, $queue);
            },
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []): ?string
    {
        $subject = $this->getSubject($queue);
        $decoded = json_decode($payload, true);
        $jobId = is_array($decoded) ? ($decoded['uuid'] ?? $decoded['id'] ?? Str::uuid()->toString()) : Str::uuid()->toString();

        $this->client()->publish($subject, $payload);

        return $jobId;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->push($job, $data, $queue);
    }

    public function pop($queue = null): ?NatsJob
    {
        if ($this->maxInFlight !== null && $this->inFlight >= $this->maxInFlight) {
            return null;
        }

        $subject = $this->getSubject($queue);
        $body = null;

        $client = $this->client();
        $client->subscribe($subject, function ($payload, $_replyTo) use (&$body): void {
            if ($body !== null) {
                return;
            }
            $body = $payload->body;
        });

        $end = microtime(true) + $this->popBlockSeconds;
        while ($body === null && microtime(true) < $end) {
            $remaining = $end - microtime(true);
            if ($remaining <= 0) {
                break;
            }
            $client->process(min(0.05, $remaining));
        }

        $client->unsubscribe($subject);

        if ($body === null) {
            return null;
        }

        return new NatsJob(
            container: $this->container,
            nats: $this,
            job: $body,
            connectionName: $this->connectionName,
            queue: $this->getQueue($queue),
        );
    }

    /**
     * @param string|null $queue
     */
    public function getQueue($queue = null): string
    {
        return $queue ?: $this->defaultQueue;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getMaxTries(): int
    {
        return $this->maxTries;
    }

    public function getDefaultRetryConfiguration(): RetryConfiguration
    {
        return new RetryConfiguration(
            maxTries: $this->maxTries,
            retryDelay: $this->retryAfter,
        );
    }

    public function getDeadLetterQueueSubject(): ?string
    {
        return $this->deadLetterQueue;
    }

    public function setDeadLetterQueueSubject(?string $subject): void
    {
        $this->deadLetterQueue = $subject;
    }

    public function publishRawToSubject(string $subject, string $payload): void
    {
        $this->client()->publish($subject, $payload);
    }

    public function notifyJobHandled(): void
    {
        if ($this->inFlight > 0) {
            $this->inFlight--;
        }
    }

    /**
     * Current number of jobs popped and not yet acknowledged via {@see notifyJobHandled()} (per process).
     */
    public function getInFlightCount(): int
    {
        return $this->inFlight;
    }

    /**
     * Configured cap for {@see getInFlightCount()}, or null when unlimited.
     */
    public function getMaxInFlightLimit(): ?int
    {
        return $this->maxInFlight;
    }

    /**
     * Expose the basis client for diagnostics (not used by {@see NatsJob}).
     */
    public function getBasisClient(): Client
    {
        return $this->client();
    }

    protected function getSubject(?string $queue = null): string
    {
        return $this->subjectPrefix . $this->getQueue($queue);
    }

    private function client(): Client
    {
        return $this->connections->connection($this->basisConnectionName);
    }
}
