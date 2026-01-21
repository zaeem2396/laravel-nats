<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;
use Throwable;

/**
 * NatsJob wraps a NATS message as a Laravel queue job.
 *
 * This class provides the interface that Laravel's queue worker expects,
 * allowing NATS messages to be processed using standard Laravel patterns.
 */
class NatsJob extends Job implements JobContract
{
    /**
     * The NATS queue instance.
     */
    protected NatsQueue $nats;

    /**
     * The raw job payload.
     */
    protected string $job;

    /**
     * The decoded job payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $decoded = null;

    /**
     * Indicates if the job has been marked as failed.
     *
     * @var bool
     */
    protected $hasFailed = false;

    /**
     * The exception that caused the job to fail.
     */
    protected ?Throwable $failureException = null;

    /**
     * Create a new job instance.
     *
     * @param Container $container
     * @param NatsQueue $nats
     * @param string $job
     * @param string $connectionName
     * @param string $queue
     */
    public function __construct(
        Container $container,
        NatsQueue $nats,
        string $job,
        string $connectionName,
        string $queue,
    ) {
        $this->container = $container;
        $this->nats = $nats;
        $this->job = $job;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    /**
     * Fire the job.
     *
     * This method executes the job handler registered for this job.
     *
     * @return void
     */
    public function fire(): void
    {
        parent::fire();
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        // Increment attempts in the payload
        $payload = $this->payload();
        $payload['attempts'] = ($payload['attempts'] ?? 1) + 1;
        $newPayload = json_encode($payload);

        // json_encode returns false on failure, but this shouldn't happen for a valid payload
        if ($newPayload === false) {
            $newPayload = $this->job;
        }

        // Re-publish the job to the queue
        if ($delay > 0) {
            $this->nats->later($delay, $newPayload, '', $this->queue);
        } else {
            $this->nats->pushRaw($newPayload, $this->queue);
        }
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete(): void
    {
        parent::delete();

        // In NATS Core, messages are automatically removed after delivery
        // No additional action needed
    }

    /**
     * Mark the job as failed.
     *
     * @param Throwable|null $exception
     *
     * @return void
     */
    public function fail($exception = null): void
    {
        $this->markAsFailed();
        $this->failureException = $exception;

        // Call parent fail if available (Laravel 10+)
        if (method_exists(parent::class, 'fail')) {
            parent::fail($exception);
        } else {
            $this->delete();
        }
    }

    /**
     * Mark the job as failed internally.
     *
     * @return void
     */
    public function markAsFailed(): void
    {
        $this->hasFailed = true;
    }

    /**
     * Determine if the job has been marked as a failure.
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->hasFailed;
    }

    /**
     * Get the exception that caused the job to fail.
     *
     * @return Throwable|null
     */
    public function getFailureException(): ?Throwable
    {
        return $this->failureException;
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return Arr::get($this->payload(), 'attempts', 1);
    }

    /**
     * Get the maximum number of attempts allowed.
     *
     * @return int|null
     */
    public function maxTries(): ?int
    {
        return Arr::get($this->payload(), 'maxTries');
    }

    /**
     * Get the maximum number of exceptions allowed.
     *
     * @return int|null
     */
    public function maxExceptions(): ?int
    {
        return Arr::get($this->payload(), 'maxExceptions');
    }

    /**
     * Get the number of seconds until job should timeout.
     *
     * @return int|null
     */
    public function timeout(): ?int
    {
        return Arr::get($this->payload(), 'timeout');
    }

    /**
     * Get the timestamp indicating when the job should timeout.
     *
     * @return int|null
     */
    public function retryUntil(): ?int
    {
        return Arr::get($this->payload(), 'retryUntil');
    }

    /**
     * Determine if the job should fail when it timeouts.
     *
     * @return bool
     */
    public function shouldFailOnTimeout(): bool
    {
        return (bool) Arr::get($this->payload(), 'failOnTimeout', false);
    }

    /**
     * Get the backoff strategy for the job.
     *
     * @return array<int>|int|null
     */
    public function backoff(): array|int|null
    {
        return Arr::get($this->payload(), 'backoff');
    }

    /**
     * Calculate the delay for the next retry attempt.
     *
     * Supports:
     * - Fixed delay (int)
     * - Linear backoff (array of delays)
     * - Exponential backoff (via retryAfter with multiplier)
     *
     * @return int The delay in seconds
     */
    public function getRetryDelay(): int
    {
        $backoff = $this->backoff();

        // If backoff is an array, use the attempt-indexed delay
        if (is_array($backoff)) {
            $attempt = $this->attempts() - 1; // 0-indexed

            return $backoff[min($attempt, count($backoff) - 1)] ?? 0;
        }

        // If backoff is an integer, use it directly
        if (is_int($backoff)) {
            return $backoff;
        }

        // Fall back to the queue's retry_after setting
        return $this->nats->getRetryAfter();
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId(): string
    {
        return Arr::get($this->payload(), 'uuid', Arr::get($this->payload(), 'id', ''));
    }

    /**
     * Get the raw body of the job.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->job;
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        if ($this->decoded === null) {
            $this->decoded = json_decode($this->job, true) ?? [];
        }

        return $this->decoded;
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Get the NATS queue instance.
     *
     * @return NatsQueue
     */
    public function getNatsQueue(): NatsQueue
    {
        return $this->nats;
    }

    /**
     * Get the name of the job's handler class.
     *
     * @return string
     */
    public function getName(): string
    {
        return Arr::get($this->payload(), 'displayName', '');
    }

    /**
     * Get the resolved name of the job.
     *
     * @return string
     */
    public function resolveName(): string
    {
        return Arr::get($this->payload(), 'displayName', $this->getName());
    }

    /**
     * Get the underlying NATS connection name.
     *
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Determine if the job should be deleted when models are missing.
     *
     * @return bool
     */
    public function shouldDeleteWhenMissingModels(): bool
    {
        return (bool) Arr::get($this->payload(), 'deleteWhenMissingModels', true);
    }

    /**
     * Check if the maximum attempts have been exceeded.
     *
     * @return bool
     */
    public function hasExceededMaxAttempts(): bool
    {
        $maxTries = $this->maxTries();

        if ($maxTries === null) {
            return false;
        }

        return $this->attempts() >= $maxTries;
    }

    /**
     * Check if the maximum exceptions have been exceeded.
     *
     * @return bool
     */
    public function hasExceededMaxExceptions(): bool
    {
        $maxExceptions = $this->maxExceptions();

        if ($maxExceptions === null) {
            return false;
        }

        return $this->attempts() >= $maxExceptions;
    }

    /**
     * Check if the job has exceeded its retry deadline.
     *
     * @return bool
     */
    public function hasExceededRetryDeadline(): bool
    {
        $retryUntil = $this->retryUntil();

        if ($retryUntil === null) {
            return false;
        }

        return time() >= $retryUntil;
    }

    /**
     * Check if the job can be retried.
     *
     * @return bool
     */
    public function canRetry(): bool
    {
        // Cannot retry if already failed
        if ($this->hasFailed()) {
            return false;
        }

        // Cannot retry if max attempts exceeded
        if ($this->hasExceededMaxAttempts()) {
            return false;
        }

        // Cannot retry if retry deadline exceeded
        if ($this->hasExceededRetryDeadline()) {
            return false;
        }

        return true;
    }
}
