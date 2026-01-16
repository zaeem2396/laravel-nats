<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;

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
     * Release the job back into the queue.
     *
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        // Re-publish the job to the queue
        if ($delay > 0) {
            $this->nats->later($delay, $this->job, '', $this->queue);
        } else {
            $this->nats->pushRaw($this->job, $this->queue);
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
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        return Arr::get($this->payload(), 'attempts', 1);
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
}
