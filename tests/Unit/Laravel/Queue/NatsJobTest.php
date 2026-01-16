<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LaravelNats\Laravel\Queue\NatsJob;
use LaravelNats\Laravel\Queue\NatsQueue;

/**
 * Create a mock NatsQueue for testing.
 */
function createMockQueue(): \Mockery\MockInterface
{
    $queue = Mockery::mock(NatsQueue::class);
    $queue->shouldReceive('getQueue')->andReturn('test-queue');

    return $queue;
}

/**
 * Create a standard test payload.
 */
function createTestPayload(): string
{
    return json_encode([
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
}

/**
 * Create a NatsJob instance for testing.
 */
function createTestJob(?string $payload = null, ?\Mockery\MockInterface $queue = null): NatsJob
{
    return new NatsJob(
        container: new Container(),
        nats: $queue ?? createMockQueue(),
        job: $payload ?? createTestPayload(),
        connectionName: 'nats',
        queue: 'test-queue',
    );
}

afterEach(function (): void {
    Mockery::close();
});

describe('NatsJob', function (): void {
    describe('getJobId', function (): void {
        it('returns the job id from uuid field', function (): void {
            $job = createTestJob();
            expect($job->getJobId())->toBe('job-uuid-123');
        });

        it('falls back to id field if uuid not present', function (): void {
            $payload = json_encode(['id' => 'fallback-id']);
            $job = createTestJob($payload);

            expect($job->getJobId())->toBe('fallback-id');
        });

        it('returns empty string if no id present', function (): void {
            $payload = json_encode(['displayName' => 'Test']);
            $job = createTestJob($payload);

            expect($job->getJobId())->toBe('');
        });
    });

    describe('attempts', function (): void {
        it('returns the number of attempts', function (): void {
            $job = createTestJob();
            expect($job->attempts())->toBe(2);
        });

        it('defaults to 1 attempt if not specified', function (): void {
            $payload = json_encode(['uuid' => 'test']);
            $job = createTestJob($payload);

            expect($job->attempts())->toBe(1);
        });
    });

    describe('getRawBody', function (): void {
        it('returns the raw body', function (): void {
            $payload = createTestPayload();
            $job = createTestJob($payload);
            expect($job->getRawBody())->toBe($payload);
        });

        it('returns exactly what was passed to constructor', function (): void {
            $rawPayload = '{"custom":"payload"}';
            $job = createTestJob($rawPayload);

            expect($job->getRawBody())->toBe($rawPayload);
        });
    });

    describe('getQueue', function (): void {
        it('returns the queue name', function (): void {
            $job = createTestJob();
            expect($job->getQueue())->toBe('test-queue');
        });
    });

    describe('payload', function (): void {
        it('returns the decoded payload', function (): void {
            $job = createTestJob();
            $payload = $job->payload();

            expect($payload)->toBeArray();
            expect($payload['uuid'])->toBe('job-uuid-123');
            expect($payload['displayName'])->toBe('App\\Jobs\\TestJob');
        });

        it('caches the decoded payload', function (): void {
            $job = createTestJob();
            $first = $job->payload();
            $second = $job->payload();

            expect($first)->toBe($second);
        });

        it('returns empty array for invalid json', function (): void {
            $job = createTestJob('not-valid-json');

            expect($job->payload())->toBe([]);
        });
    });

    describe('getNatsQueue', function (): void {
        it('returns the nats queue instance', function (): void {
            $queue = createMockQueue();
            $job = createTestJob(null, $queue);
            expect($job->getNatsQueue())->toBe($queue);
        });
    });

    describe('release', function (): void {
        it('releases the job back to queue without delay', function (): void {
            $payload = createTestPayload();
            $queue = createMockQueue();
            $queue->shouldReceive('pushRaw')
                ->once()
                ->with($payload, 'test-queue')
                ->andReturn('job-uuid-123');

            $job = createTestJob($payload, $queue);
            $job->release();

            expect($job->isReleased())->toBeTrue();
        });

        it('releases the job with delay', function (): void {
            $payload = createTestPayload();
            $queue = createMockQueue();
            $queue->shouldReceive('later')
                ->once()
                ->with(30, $payload, '', 'test-queue')
                ->andReturn('job-uuid-123');

            $job = createTestJob($payload, $queue);
            $job->release(30);

            expect($job->isReleased())->toBeTrue();
        });

        it('calls parent release method', function (): void {
            $queue = createMockQueue();
            $queue->shouldReceive('pushRaw')->andReturn('job-uuid-123');

            $job = createTestJob(null, $queue);
            $job->release();

            expect($job->isReleased())->toBeTrue();
        });
    });

    describe('delete', function (): void {
        it('deletes the job', function (): void {
            $job = createTestJob();
            $job->delete();

            expect($job->isDeleted())->toBeTrue();
        });

        it('marks job as deleted', function (): void {
            $job = createTestJob();
            expect($job->isDeleted())->toBeFalse();

            $job->delete();

            expect($job->isDeleted())->toBeTrue();
        });
    });

    describe('connection name', function (): void {
        it('stores the connection name', function (): void {
            $payload = json_encode(['uuid' => 'test']);
            $job = new NatsJob(
                container: new Container(),
                nats: createMockQueue(),
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

            $job = createTestJob($payload);
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

            $job = createTestJob($payload);

            expect($job->payload()['message'])->toContain('🎉');
        });
    });
});
