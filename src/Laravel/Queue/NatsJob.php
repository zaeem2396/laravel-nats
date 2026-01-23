<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use LaravelNats\Laravel\Queue\Failed\NatsFailedJobProvider;
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

        // Resolve the job instance to call failed() method
        $this->resolveAndCallFailed($exception);

        // Store in failed_jobs table
        $this->storeFailedJob($exception);

        // Route to Dead Letter Queue if configured
        $this->routeToDeadLetterQueue($exception);

        // Fire Laravel's JobFailed event
        Event::dispatch(new JobFailed(
            $this->connectionName,
            $this,
            $exception ?? new \RuntimeException('Job failed without exception')
        ));

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
     * Get the backoff configuration from the job payload.
     *
     * @return array<int>|int|null
     */
    public function backoff(): array|int|null
    {
        return Arr::get($this->payload(), 'backoff');
    }

    /**
     * Get the backoff strategy for this job.
     *
     * @return BackoffStrategy
     */
    public function getBackoffStrategy(): BackoffStrategy
    {
        return BackoffStrategy::fromBackoff(
            $this->backoff(),
            $this->nats->getRetryAfter(),
        );
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
        return $this->getBackoffStrategy()->getDelay($this->attempts());
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

    /**
     * Get the retry configuration for this job.
     *
     * @return RetryConfiguration
     */
    public function getRetryConfiguration(): RetryConfiguration
    {
        return RetryConfiguration::fromPayload(
            $this->payload(),
            RetryConfiguration::DEFAULT_MAX_TRIES,
            $this->nats->getRetryAfter(),
        );
    }

    /**
     * Release the job with automatic retry delay calculation.
     *
     * This method uses the job's backoff configuration to determine
     * the appropriate delay for the next retry attempt.
     *
     * @return void
     */
    public function releaseWithBackoff(): void
    {
        $delay = $this->getRetryDelay();
        $this->release($delay);
    }

    /**
     * Get remaining attempts before the job will fail.
     *
     * @return int|null Returns null if maxTries is not set
     */
    public function remainingAttempts(): ?int
    {
        $maxTries = $this->maxTries();

        if ($maxTries === null) {
            return null;
        }

        return max(0, $maxTries - $this->attempts());
    }

    /**
     * Check if this is the final attempt.
     *
     * @return bool
     */
    public function isFinalAttempt(): bool
    {
        $maxTries = $this->maxTries();

        if ($maxTries === null) {
            return false;
        }

        return $this->attempts() >= $maxTries;
    }

    /**
     * Resolve the job instance and call its failed() method if it exists.
     *
     * @param Throwable|null $exception
     *
     * @return void
     */
    protected function resolveAndCallFailed(?Throwable $exception): void
    {
        try {
            $payload = $this->payload();
            $job = $this->resolveJob();

            if ($job && method_exists($job, 'failed')) {
                $job->failed($exception ?? new \RuntimeException('Job failed without exception'));
            }
        } catch (Throwable $e) {
            // Silently fail if we can't resolve the job
            // This prevents cascading failures
        }
    }

    /**
     * Resolve the job instance from the payload.
     *
     * @return object|null
     */
    protected function resolveJob(): ?object
    {
        try {
            $payload = $this->payload();

            if (! isset($payload['data']['commandName'])) {
                return null;
            }

            $command = unserialize($payload['data']['command']);

            return $command;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Store the failed job in the database.
     *
     * @param Throwable|null $exception
     *
     * @return void
     */
    protected function storeFailedJob(?Throwable $exception): void
    {
        try {
            $provider = $this->getFailedJobProvider();

            if ($provider) {
                $provider->log(
                    $this->connectionName,
                    $this->queue,
                    $this->job,
                    $exception ?? new \RuntimeException('Job failed without exception')
                );
            }
        } catch (Throwable $e) {
            // Silently fail if we can't store the failed job
            // Log the error but don't throw
        }
    }

    /**
     * Route the failed job to the Dead Letter Queue if configured.
     *
     * @param Throwable|null $exception
     *
     * @return void
     */
    protected function routeToDeadLetterQueue(?Throwable $exception): void
    {
        $dlqSubject = $this->nats->getDeadLetterQueueSubject();

        if ($dlqSubject === null) {
            return;
        }

        try {
            // Create enhanced payload with failure metadata
            $payload = $this->payload();
            $payload['failed_at'] = time();
            $payload['failure_exception'] = $exception ? (string) $exception : null;
            $payload['failure_message'] = $exception?->getMessage();
            $payload['failure_trace'] = $exception?->getTraceAsString();
            $payload['original_queue'] = $this->queue;
            $payload['original_connection'] = $this->connectionName;

            $dlqPayload = json_encode($payload);

            // Publish to DLQ
            $this->nats->getClient()->publishRaw($dlqSubject, $dlqPayload);
        } catch (Throwable $e) {
            // Silently fail if we can't route to DLQ
        }
    }

    /**
     * Get the failed job provider instance.
     *
     * @return NatsFailedJobProvider|null
     */
    protected function getFailedJobProvider(): ?NatsFailedJobProvider
    {
        try {
            $config = $this->container->make('config');
            $connection = $config->get('queue.failed.connection');
            $table = $config->get('queue.failed.table', 'failed_jobs');

            return new NatsFailedJobProvider($connection, $table);
        } catch (Throwable $e) {
            return null;
        }
    }
}
