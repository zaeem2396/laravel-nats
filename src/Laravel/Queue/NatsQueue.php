<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use LaravelNats\Core\Client;

/**
 * NatsQueue implements Laravel's Queue contract using NATS as the backend.
 *
 * This queue implementation uses NATS subjects as queue names.
 * Messages are published to subjects and consumed by workers.
 */
class NatsQueue extends Queue implements QueueContract
{
    /**
     * The NATS client.
     */
    protected Client $client;

    /**
     * The default queue name.
     */
    protected string $defaultQueue;

    /**
     * The retry after value in seconds.
     */
    protected int $retryAfter;

    /**
     * The subject prefix for queue messages.
     */
    protected string $subjectPrefix = 'laravel.queue.';

    /**
     * The default maximum number of attempts.
     */
    protected int $maxTries;

    /**
     * The Dead Letter Queue subject (optional).
     */
    protected ?string $deadLetterQueue = null;

    /**
     * Create a new NATS queue instance.
     *
     * @param Client $client
     * @param string $defaultQueue
     * @param int $retryAfter
     * @param int $maxTries
     * @param string|null $deadLetterQueue
     */
    public function __construct(
        Client $client,
        string $defaultQueue = 'default',
        int $retryAfter = 60,
        int $maxTries = 3,
        ?string $deadLetterQueue = null,
    ) {
        $this->client = $client;
        $this->defaultQueue = $defaultQueue;
        $this->retryAfter = $retryAfter;
        $this->maxTries = $maxTries;
        $this->deadLetterQueue = $deadLetterQueue;
    }

    /**
     * Get the size of the queue.
     *
     * Note: NATS Core does not track queue size. This requires JetStream.
     *
     * @param string|null $queue
     *
     * @return int
     */
    public function size($queue = null): int
    {
        // NATS Core does not provide queue size
        // This will be implemented with JetStream in Phase 3
        return 0;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param object|string $job
     * @param mixed $data
     * @param string|null $queue
     *
     * @return string|null The job ID
     */
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

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string|null $queue
     * @param array<string, mixed> $options
     *
     * @return string|null The job ID
     */
    public function pushRaw($payload, $queue = null, array $options = []): ?string
    {
        $subject = $this->getSubject($queue);

        // Decode to extract job ID
        $decoded = json_decode($payload, true);
        $jobId = $decoded['uuid'] ?? $decoded['id'] ?? Str::uuid()->toString();

        $this->client->publishRaw($subject, $payload);

        return $jobId;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * Note: Delayed jobs require JetStream. This is a stub for Phase 2.2.
     *
     * @param DateTimeInterface|DateInterval|int $delay
     * @param object|string $job
     * @param mixed $data
     * @param string|null $queue
     *
     * @return string|null
     */
    public function later($delay, $job, $data = '', $queue = null): ?string
    {
        // Delayed jobs will be implemented with JetStream in Phase 2.2
        // For now, push immediately with a warning
        return $this->push($job, $data, $queue);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     *
     * @return NatsJob|null
     */
    public function pop($queue = null): ?NatsJob
    {
        $subject = $this->getSubject($queue);
        $job = null;

        // Subscribe to receive one message
        $sid = $this->client->subscribe($subject, function ($message) use (&$job): void {
            $job = $message;
        });

        // Process for a short time to receive a message
        $this->client->process(0.1);

        // Unsubscribe
        $this->client->unsubscribe($sid);

        if ($job === null) {
            return null;
        }

        $payload = $job->getPayload();

        return new NatsJob(
            container: $this->container,
            nats: $this,
            job: $payload,
            connectionName: $this->connectionName,
            queue: $this->getQueue($queue),
        );
    }

    /**
     * Get the queue or return the default.
     *
     * @param string|null $queue
     *
     * @return string
     */
    public function getQueue($queue = null): string
    {
        return $queue ?: $this->defaultQueue;
    }

    /**
     * Get the NATS client.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get the retry after value.
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get the default maximum number of attempts.
     *
     * @return int
     */
    public function getMaxTries(): int
    {
        return $this->maxTries;
    }

    /**
     * Get the default retry configuration.
     *
     * @return RetryConfiguration
     */
    public function getDefaultRetryConfiguration(): RetryConfiguration
    {
        return new RetryConfiguration(
            maxTries: $this->maxTries,
            retryDelay: $this->retryAfter,
        );
    }

    /**
     * Get the Dead Letter Queue subject.
     *
     * @return string|null
     */
    public function getDeadLetterQueueSubject(): ?string
    {
        return $this->deadLetterQueue;
    }

    /**
     * Set the Dead Letter Queue subject.
     *
     * @param string|null $subject
     *
     * @return void
     */
    public function setDeadLetterQueueSubject(?string $subject): void
    {
        $this->deadLetterQueue = $subject;
    }

    /**
     * Get the NATS subject for a queue.
     *
     * @param string|null $queue
     *
     * @return string
     */
    protected function getSubject(?string $queue = null): string
    {
        return $this->subjectPrefix . $this->getQueue($queue);
    }
}
