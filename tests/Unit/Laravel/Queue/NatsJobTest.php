<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LaravelNats\Laravel\Queue\NatsJob;
use LaravelNats\Laravel\Queue\NatsQueue;

describe('NatsJob', function (): void {
    beforeEach(function (): void {
        $this->container = new Container();

        // Create a mock queue (we don't need actual NATS for unit tests)
        $this->queue = Mockery::mock(NatsQueue::class);
        $this->queue->shouldReceive('getQueue')->andReturn('test-queue');

        $this->payload = json_encode([
            'uuid' => 'job-uuid-123',
            'displayName' => 'App\\Jobs\\TestJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => 3,
            'attempts' => 2,
            'data' => [
                'commandName' => 'App\\Jobs\\TestJob',
                'command' => 'serialized-command-data',
            ],
        ]);

        $this->job = new NatsJob(
            container: $this->container,
            nats: $this->queue,
            job: $this->payload,
            connectionName: 'nats',
            queue: 'test-queue',
        );
    });

    afterEach(function (): void {
        Mockery::close();
    });

    describe('getJobId', function (): void {
        it('returns the job id from uuid field', function (): void {
            expect($this->job->getJobId())->toBe('job-uuid-123');
        });

        it('falls back to id field if uuid not present', function (): void {
            $payload = json_encode(['id' => 'fallback-id']);
            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: $payload,
                connectionName: 'nats',
                queue: 'test-queue',
            );

            expect($job->getJobId())->toBe('fallback-id');
        });

        it('returns empty string if no id present', function (): void {
            $payload = json_encode(['displayName' => 'Test']);
            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: $payload,
                connectionName: 'nats',
                queue: 'test-queue',
            );

            expect($job->getJobId())->toBe('');
        });
    });

    describe('attempts', function (): void {
        it('returns the number of attempts', function (): void {
            expect($this->job->attempts())->toBe(2);
        });

        it('defaults to 1 attempt if not specified', function (): void {
            $payload = json_encode(['uuid' => 'test']);
            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: $payload,
                connectionName: 'nats',
                queue: 'test-queue',
            );

            expect($job->attempts())->toBe(1);
        });
    });

    describe('getRawBody', function (): void {
        it('returns the raw body', function (): void {
            expect($this->job->getRawBody())->toBe($this->payload);
        });

        it('returns exactly what was passed to constructor', function (): void {
            $rawPayload = '{"custom":"payload"}';
            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: $rawPayload,
                connectionName: 'nats',
                queue: 'test-queue',
            );

            expect($job->getRawBody())->toBe($rawPayload);
        });
    });

    describe('getQueue', function (): void {
        it('returns the queue name', function (): void {
            expect($this->job->getQueue())->toBe('test-queue');
        });
    });

    describe('payload', function (): void {
        it('returns the decoded payload', function (): void {
            $payload = $this->job->payload();

            expect($payload)->toBeArray();
            expect($payload['uuid'])->toBe('job-uuid-123');
            expect($payload['displayName'])->toBe('App\\Jobs\\TestJob');
        });

        it('caches the decoded payload', function (): void {
            $first = $this->job->payload();
            $second = $this->job->payload();

            expect($first)->toBe($second);
        });

        it('returns empty array for invalid json', function (): void {
            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: 'not-valid-json',
                connectionName: 'nats',
                queue: 'test-queue',
            );

            expect($job->payload())->toBe([]);
        });
    });

    describe('getNatsQueue', function (): void {
        it('returns the nats queue instance', function (): void {
            expect($this->job->getNatsQueue())->toBe($this->queue);
        });
    });

    describe('release', function (): void {
        it('releases the job back to queue without delay', function (): void {
            $this->queue->shouldReceive('pushRaw')
                ->once()
                ->with($this->payload, 'test-queue')
                ->andReturn('job-uuid-123');

            $this->job->release();

            expect($this->job->isReleased())->toBeTrue();
        });

        it('releases the job with delay', function (): void {
            $this->queue->shouldReceive('later')
                ->once()
                ->with(30, $this->payload, '', 'test-queue')
                ->andReturn('job-uuid-123');

            $this->job->release(30);

            expect($this->job->isReleased())->toBeTrue();
        });

        it('calls parent release method', function (): void {
            $this->queue->shouldReceive('pushRaw')->andReturn('job-uuid-123');

            $this->job->release();

            expect($this->job->isReleased())->toBeTrue();
        });
    });

    describe('delete', function (): void {
        it('deletes the job', function (): void {
            $this->job->delete();

            expect($this->job->isDeleted())->toBeTrue();
        });

        it('marks job as deleted', function (): void {
            expect($this->job->isDeleted())->toBeFalse();

            $this->job->delete();

            expect($this->job->isDeleted())->toBeTrue();
        });
    });

    describe('connection name', function (): void {
        it('stores the connection name', function (): void {
            // The connection name is stored but accessed via parent class
            $payload = json_encode(['uuid' => 'test']);
            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: $payload,
                connectionName: 'my-nats-connection',
                queue: 'test-queue',
            );

            expect($job->getConnectionName())->toBe('my-nats-connection');
        });
    });

    describe('complex payloads', function (): void {
        it('handles nested data structures', function (): void {
            $payload = json_encode([
                'uuid' => 'nested-job',
                'data' => [
                    'users' => [
                        ['id' => 1, 'name' => 'John'],
                        ['id' => 2, 'name' => 'Jane'],
                    ],
                    'metadata' => [
                        'created_at' => '2026-01-16',
                        'tags' => ['important', 'urgent'],
                    ],
                ],
            ]);

            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: $payload,
                connectionName: 'nats',
                queue: 'test-queue',
            );

            $decoded = $job->payload();

            expect($decoded['data']['users'])->toHaveCount(2);
            expect($decoded['data']['users'][0]['name'])->toBe('John');
            expect($decoded['data']['metadata']['tags'])->toContain('important');
        });

        it('handles special characters in payload', function (): void {
            $payload = json_encode([
                'uuid' => 'special-chars',
                'message' => 'Hello, World! 🎉 Special: <>&"\'',
            ]);

            $job = new NatsJob(
                container: $this->container,
                nats: $this->queue,
                job: $payload,
                connectionName: 'nats',
                queue: 'test-queue',
            );

            expect($job->payload()['message'])->toContain('🎉');
        });
    });
});
