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
    describe('markAsFailed', function (): void {
        it('marks job as failed', function (): void {
            $job = createTestJob();

            expect($job->hasFailed())->toBeFalse();

            $job->markAsFailed();

            expect($job->hasFailed())->toBeTrue();
        });

        it('can be checked multiple times', function (): void {
            $job = createTestJob();

            $job->markAsFailed();

            expect($job->hasFailed())->toBeTrue();
            expect($job->hasFailed())->toBeTrue();
        });
    });

    describe('maxTries', function (): void {
        it('returns maxTries from payload', function (): void {
            $job = createTestJob();
            expect($job->maxTries())->toBe(3);
        });

        it('returns null if maxTries not in payload', function (): void {
            $payload = json_encode(['uuid' => 'test']);
            $job = createTestJob($payload);

            expect($job->maxTries())->toBeNull();
        });
    });

    describe('maxExceptions', function (): void {
        it('returns maxExceptions from payload', function (): void {
            $payload = json_encode(['uuid' => 'test', 'maxExceptions' => 5]);
            $job = createTestJob($payload);

            expect($job->maxExceptions())->toBe(5);
        });

        it('returns null if maxExceptions not in payload', function (): void {
            $job = createTestJob();
            expect($job->maxExceptions())->toBeNull();
        });
    });

    describe('timeout', function (): void {
        it('returns timeout from payload', function (): void {
            $payload = json_encode(['uuid' => 'test', 'timeout' => 120]);
            $job = createTestJob($payload);

            expect($job->timeout())->toBe(120);
        });

        it('returns null if timeout not in payload', function (): void {
            $job = createTestJob();
            expect($job->timeout())->toBeNull();
        });
    });

    describe('retryUntil', function (): void {
        it('returns retryUntil from payload', function (): void {
            $payload = json_encode(['uuid' => 'test', 'retryUntil' => 1700000000]);
            $job = createTestJob($payload);

            expect($job->retryUntil())->toBe(1700000000);
        });

        it('returns null if retryUntil not in payload', function (): void {
            $job = createTestJob();
            expect($job->retryUntil())->toBeNull();
        });
    });

    describe('shouldFailOnTimeout', function (): void {
        it('returns true when failOnTimeout is set', function (): void {
            $payload = json_encode(['uuid' => 'test', 'failOnTimeout' => true]);
            $job = createTestJob($payload);

            expect($job->shouldFailOnTimeout())->toBeTrue();
        });

        it('returns false by default', function (): void {
            $job = createTestJob();
            expect($job->shouldFailOnTimeout())->toBeFalse();
        });
    });

    describe('backoff', function (): void {
        it('returns integer backoff', function (): void {
            $payload = json_encode(['uuid' => 'test', 'backoff' => 60]);
            $job = createTestJob($payload);

            expect($job->backoff())->toBe(60);
        });

        it('returns array backoff', function (): void {
            $payload = json_encode(['uuid' => 'test', 'backoff' => [10, 30, 60]]);
            $job = createTestJob($payload);

            expect($job->backoff())->toBe([10, 30, 60]);
        });

        it('returns null if backoff not in payload', function (): void {
            $job = createTestJob();
            expect($job->backoff())->toBeNull();
        });
    });

    describe('getRetryDelay', function (): void {
        it('returns fixed delay for integer backoff', function (): void {
            $payload = json_encode(['uuid' => 'test', 'backoff' => 30, 'attempts' => 1]);
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(60);
            $job = createTestJob($payload, $queue);

            expect($job->getRetryDelay())->toBe(30);
        });

        it('returns indexed delay for array backoff', function (): void {
            // Attempt 2 should get the 2nd delay (index 1)
            $payload = json_encode(['uuid' => 'test', 'backoff' => [10, 30, 60], 'attempts' => 2]);
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(60);
            $job = createTestJob($payload, $queue);

            expect($job->getRetryDelay())->toBe(30);
        });

        it('falls back to queue retry_after', function (): void {
            $payload = json_encode(['uuid' => 'test', 'attempts' => 1]);
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(45);
            $job = createTestJob($payload, $queue);

            expect($job->getRetryDelay())->toBe(45);
        });
    });

    describe('hasExceededMaxAttempts', function (): void {
        it('returns false when under max attempts', function (): void {
            $job = createTestJob(); // maxTries: 3, attempts: 2

            expect($job->hasExceededMaxAttempts())->toBeFalse();
        });

        it('returns true when at max attempts', function (): void {
            $payload = json_encode(['uuid' => 'test', 'maxTries' => 3, 'attempts' => 3]);
            $job = createTestJob($payload);

            expect($job->hasExceededMaxAttempts())->toBeTrue();
        });

        it('returns false when maxTries not set', function (): void {
            $payload = json_encode(['uuid' => 'test', 'attempts' => 100]);
            $job = createTestJob($payload);

            expect($job->hasExceededMaxAttempts())->toBeFalse();
        });
    });

    describe('canRetry', function (): void {
        it('returns true when can retry', function (): void {
            $job = createTestJob(); // maxTries: 3, attempts: 2

            expect($job->canRetry())->toBeTrue();
        });

        it('returns false when max attempts exceeded', function (): void {
            $payload = json_encode(['uuid' => 'test', 'maxTries' => 2, 'attempts' => 3]);
            $job = createTestJob($payload);

            expect($job->canRetry())->toBeFalse();
        });

        it('returns false when job has failed', function (): void {
            $job = createTestJob();
            $job->markAsFailed();

            expect($job->canRetry())->toBeFalse();
        });
    });

    describe('remainingAttempts', function (): void {
        it('returns remaining attempts', function (): void {
            $job = createTestJob(); // maxTries: 3, attempts: 2

            expect($job->remainingAttempts())->toBe(1);
        });

        it('returns null when maxTries not set', function (): void {
            $payload = json_encode(['uuid' => 'test', 'attempts' => 2]);
            $job = createTestJob($payload);

            expect($job->remainingAttempts())->toBeNull();
        });

        it('returns zero when exceeded', function (): void {
            $payload = json_encode(['uuid' => 'test', 'maxTries' => 2, 'attempts' => 5]);
            $job = createTestJob($payload);

            expect($job->remainingAttempts())->toBe(0);
        });
    });

    describe('isFinalAttempt', function (): void {
        it('returns false when not final attempt', function (): void {
            $job = createTestJob(); // maxTries: 3, attempts: 2

            expect($job->isFinalAttempt())->toBeFalse();
        });

        it('returns true when on final attempt', function (): void {
            $payload = json_encode(['uuid' => 'test', 'maxTries' => 3, 'attempts' => 3]);
            $job = createTestJob($payload);

            expect($job->isFinalAttempt())->toBeTrue();
        });

        it('returns false when maxTries not set', function (): void {
            $payload = json_encode(['uuid' => 'test', 'attempts' => 100]);
            $job = createTestJob($payload);

            expect($job->isFinalAttempt())->toBeFalse();
        });
    });

    describe('getName', function (): void {
        it('returns displayName from payload', function (): void {
            $job = createTestJob();
            expect($job->getName())->toBe('App\\Jobs\\TestJob');
        });

        it('returns empty string if displayName not in payload', function (): void {
            $payload = json_encode(['uuid' => 'test']);
            $job = createTestJob($payload);

            expect($job->getName())->toBe('');
        });
    });

    describe('shouldDeleteWhenMissingModels', function (): void {
        it('returns true by default', function (): void {
            $job = createTestJob();
            expect($job->shouldDeleteWhenMissingModels())->toBeTrue();
        });

        it('returns false when set in payload', function (): void {
            $payload = json_encode(['uuid' => 'test', 'deleteWhenMissingModels' => false]);
            $job = createTestJob($payload);

            expect($job->shouldDeleteWhenMissingModels())->toBeFalse();
        });
    });

    describe('getBackoffStrategy', function (): void {
        it('returns BackoffStrategy instance', function (): void {
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(60);
            $job = createTestJob(null, $queue);

            $strategy = $job->getBackoffStrategy();

            expect($strategy)->toBeInstanceOf(\LaravelNats\Laravel\Queue\BackoffStrategy::class);
        });

        it('uses backoff from payload when available', function (): void {
            $payload = json_encode(['uuid' => 'test', 'backoff' => [10, 20, 30], 'attempts' => 1]);
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(60);
            $job = createTestJob($payload, $queue);

            $strategy = $job->getBackoffStrategy();

            expect($strategy->getType())->toBe(\LaravelNats\Laravel\Queue\BackoffStrategy::STRATEGY_LINEAR);
        });

        it('falls back to queue retry_after', function (): void {
            $payload = json_encode(['uuid' => 'test', 'attempts' => 1]);
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(45);
            $job = createTestJob($payload, $queue);

            $strategy = $job->getBackoffStrategy();

            expect($strategy->getDelay(1))->toBe(45);
        });
    });

    describe('releaseWithBackoff', function (): void {
        it('releases job with calculated delay', function (): void {
            $payload = json_encode(['uuid' => 'test', 'backoff' => 30, 'attempts' => 1]);
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(60);
            $queue->shouldReceive('later')
                ->once()
                ->withArgs(function ($delay, $payload, $data, $queueName) {
                    return $delay === 30;
                })
                ->andReturn('job-id');
            $job = createTestJob($payload, $queue);

            $job->releaseWithBackoff();

            expect($job->isReleased())->toBeTrue();
        });

        it('uses queue default when no backoff configured', function (): void {
            $payload = json_encode(['uuid' => 'test', 'attempts' => 1]);
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(45);
            $queue->shouldReceive('later')
                ->once()
                ->withArgs(function ($delay, $payload, $data, $queueName) {
                    return $delay === 45;
                })
                ->andReturn('job-id');
            $job = createTestJob($payload, $queue);

            $job->releaseWithBackoff();

            expect($job->isReleased())->toBeTrue();
        });
    });

    describe('getRetryConfiguration', function (): void {
        it('returns RetryConfiguration instance', function (): void {
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(60);
            $job = createTestJob(null, $queue);

            $config = $job->getRetryConfiguration();

            expect($config)->toBeInstanceOf(\LaravelNats\Laravel\Queue\RetryConfiguration::class);
        });

        it('uses maxTries from payload', function (): void {
            $queue = createMockQueue();
            $queue->shouldReceive('getRetryAfter')->andReturn(60);
            $job = createTestJob(null, $queue); // Default payload has maxTries: 3

            $config = $job->getRetryConfiguration();

            expect($config->getMaxTries())->toBe(3);
        });
    });

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
            $queue = createMockQueue();
            // The release method now increments attempts from 2 to 3
            $queue->shouldReceive('pushRaw')
                ->once()
                ->withArgs(function ($payload, $queueName) {
                    $decoded = json_decode($payload, true);

                    return $decoded['attempts'] === 3 && $queueName === 'test-queue';
                })
                ->andReturn('job-uuid-123');

            $job = createTestJob(null, $queue);
            $job->release();

            expect($job->isReleased())->toBeTrue();
        });

        it('releases the job with delay', function (): void {
            $queue = createMockQueue();
            // The release method now increments attempts from 2 to 3
            $queue->shouldReceive('later')
                ->once()
                ->withArgs(function ($delay, $payload, $data, $queueName) {
                    $decoded = json_decode($payload, true);

                    return $delay === 30 && $decoded['attempts'] === 3 && $queueName === 'test-queue';
                })
                ->andReturn('job-uuid-123');

            $job = createTestJob(null, $queue);
            $job->release(30);

            expect($job->isReleased())->toBeTrue();
        });

        it('increments attempts on release', function (): void {
            $queue = createMockQueue();
            $capturedPayload = null;
            $queue->shouldReceive('pushRaw')
                ->once()
                ->withArgs(function ($payload, $queueName) use (&$capturedPayload) {
                    $capturedPayload = $payload;

                    return true;
                })
                ->andReturn('job-uuid-123');

            $job = createTestJob(null, $queue);
            expect($job->attempts())->toBe(2); // Initial attempts

            $job->release();

            $decoded = json_decode($capturedPayload, true);
            expect($decoded['attempts'])->toBe(3); // Incremented
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
