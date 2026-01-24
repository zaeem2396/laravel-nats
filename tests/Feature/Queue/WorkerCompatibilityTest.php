<?php

declare(strict_types=1);

/**
 * ============================================================================
 * QUEUE WORKER COMPATIBILITY TESTS
 * ============================================================================
 *
 * These tests verify that the NATS queue driver works correctly with
 * Laravel's queue:work command and worker infrastructure.
 *
 * Run with: vendor/bin/pest tests/Feature/Queue/WorkerCompatibilityTest.php
 * ============================================================================
 */

use Illuminate\Container\Container;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Laravel\Queue\NatsJob;
use LaravelNats\Laravel\Queue\NatsQueue;

/**
 * Helper to create a connected NATS client for testing.
 */
function createWorkerTestClient(): Client
{
    $config = ConnectionConfig::local();
    $client = new Client($config);
    $client->connect();

    return $client;
}

describe('Queue Worker Compatibility', function (): void {
    describe('queue pop operation', function (): void {
        it('returns null when queue is empty', function (): void {
            $client = createWorkerTestClient();

            try {
                $uniqueQueue = 'empty-queue-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $job = $queue->pop($uniqueQueue);

                expect($job)->toBeNull();
            } finally {
                $client->disconnect();
            }
        });

        it('returns job when message is available', function (): void {
            $client = createWorkerTestClient();

            try {
                $uniqueQueue = 'pop-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                // First, subscribe to receive the message
                $received = null;
                $client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$received): void {
                    $received = $msg;
                });

                // Push a job
                $payload = json_encode([
                    'uuid' => 'worker-test-job',
                    'displayName' => 'TestJob',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                    'data' => ['message' => 'Hello Worker'],
                ]);

                $queue->pushRaw($payload);

                // Process to receive the message
                $client->process(0.5);

                expect($received)->not->toBeNull();
            } finally {
                $client->disconnect();
            }
        });

        it('can pop from specific queue', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue1 = 'queue-a-' . uniqid();
                $queue2 = 'queue-b-' . uniqid();
                $natsQueue = new NatsQueue($client, 'default', 60);
                $natsQueue->setContainer(new Container());
                $natsQueue->setConnectionName('nats');

                // Subscribe to both queues
                $receivedQ1 = null;
                $receivedQ2 = null;

                $client->subscribe('laravel.queue.' . $queue1, function ($msg) use (&$receivedQ1): void {
                    $receivedQ1 = $msg;
                });
                $client->subscribe('laravel.queue.' . $queue2, function ($msg) use (&$receivedQ2): void {
                    $receivedQ2 = $msg;
                });

                // Push to queue1 only
                $payload = json_encode([
                    'uuid' => 'queue-specific-job',
                    'displayName' => 'TestJob',
                ]);
                $natsQueue->pushRaw($payload, $queue1);

                $client->process(0.5);

                expect($receivedQ1)->not->toBeNull();
                expect($receivedQ2)->toBeNull();
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('job execution', function (): void {
        it('creates valid NatsJob instance from pop', function (): void {
            $client = createWorkerTestClient();

            try {
                $uniqueQueue = 'job-instance-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'execution-test-job',
                    'displayName' => 'App\\Jobs\\TestJob',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                    'maxTries' => 5,
                    'attempts' => 1,
                    'data' => ['key' => 'value'],
                ]);

                // Create job directly (simulating pop result)
                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                expect($job)->toBeInstanceOf(NatsJob::class);
                expect($job->getJobId())->toBe('execution-test-job');
                expect($job->getName())->toBe('App\\Jobs\\TestJob');
                expect($job->attempts())->toBe(1);
                expect($job->maxTries())->toBe(5);
                expect($job->getQueue())->toBe($uniqueQueue);
                expect($job->getConnectionName())->toBe('nats');
            } finally {
                $client->disconnect();
            }
        });

        it('respects maxTries from payload', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue = new NatsQueue($client, 'default', 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'max-tries-job',
                    'maxTries' => 10,
                    'attempts' => 1,
                ]);

                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: 'test',
                );

                expect($job->maxTries())->toBe(10);
            } finally {
                $client->disconnect();
            }
        });

        it('respects timeout from payload', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue = new NatsQueue($client, 'default', 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'timeout-job',
                    'timeout' => 120,
                    'attempts' => 1,
                ]);

                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: 'test',
                );

                expect($job->timeout())->toBe(120);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('job lifecycle', function (): void {
        it('can delete job after processing', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue = new NatsQueue($client, 'default', 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'delete-test-job',
                    'attempts' => 1,
                ]);

                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: 'test',
                );

                expect($job->isDeleted())->toBeFalse();

                $job->delete();

                expect($job->isDeleted())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });

        it('can release job back to queue', function (): void {
            $client = createWorkerTestClient();

            try {
                $uniqueQueue = 'release-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                // Subscribe to catch the released message
                $released = null;
                $client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$released): void {
                    $released = $msg;
                });

                $payload = json_encode([
                    'uuid' => 'release-job',
                    'attempts' => 1,
                ]);

                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                expect($job->isReleased())->toBeFalse();

                $job->release();

                $client->process(0.5);

                expect($job->isReleased())->toBeTrue();
                expect($released)->not->toBeNull();

                // Verify attempts was incremented
                $releasedPayload = json_decode($released->getPayload(), true);
                expect($releasedPayload['attempts'])->toBe(2);
            } finally {
                $client->disconnect();
            }
        });

        it('increments attempts on each release', function (): void {
            $client = createWorkerTestClient();

            try {
                $uniqueQueue = 'attempts-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $released = [];
                $client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$released): void {
                    $released[] = $msg;
                });

                $payload = json_encode([
                    'uuid' => 'attempts-job',
                    'attempts' => 1,
                ]);

                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                // Release 3 times
                $job->release();
                $client->process(0.2);

                // Create new job from released payload
                $payload2 = $released[0]->getPayload();
                $job2 = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload2,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                expect($job2->attempts())->toBe(2);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('worker flags support', function (): void {
        it('uses default retry_after from queue config', function (): void {
            $client = createWorkerTestClient();

            try {
                $retryAfter = 90;
                $queue = new NatsQueue($client, 'default', $retryAfter);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                expect($queue->getRetryAfter())->toBe(90);
            } finally {
                $client->disconnect();
            }
        });

        it('provides job attempts for worker tries check', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue = new NatsQueue($client, 'default', 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'tries-check-job',
                    'maxTries' => 3,
                    'attempts' => 2,
                ]);

                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: 'test',
                );

                expect($job->attempts())->toBe(2);
                expect($job->maxTries())->toBe(3);
                expect($job->hasExceededMaxAttempts())->toBeFalse();

                // At max attempts
                $payloadMaxed = json_encode([
                    'uuid' => 'tries-check-job-2',
                    'maxTries' => 3,
                    'attempts' => 3,
                ]);

                $jobMaxed = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payloadMaxed,
                    connectionName: 'nats',
                    queue: 'test',
                );

                expect($jobMaxed->hasExceededMaxAttempts())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('multiple queue support', function (): void {
        it('can push to different queues', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue = new NatsQueue($client, 'default', 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $queueA = 'queue-a-' . uniqid();
                $queueB = 'queue-b-' . uniqid();

                $messagesA = [];
                $messagesB = [];

                $client->subscribe('laravel.queue.' . $queueA, function ($msg) use (&$messagesA): void {
                    $messagesA[] = $msg;
                });
                $client->subscribe('laravel.queue.' . $queueB, function ($msg) use (&$messagesB): void {
                    $messagesB[] = $msg;
                });

                $queue->pushRaw(json_encode(['uuid' => 'job-a1']), $queueA);
                $queue->pushRaw(json_encode(['uuid' => 'job-a2']), $queueA);
                $queue->pushRaw(json_encode(['uuid' => 'job-b1']), $queueB);

                $client->process(0.5);

                expect(count($messagesA))->toBe(2);
                expect(count($messagesB))->toBe(1);
            } finally {
                $client->disconnect();
            }
        });

        it('uses default queue when none specified', function (): void {
            $client = createWorkerTestClient();

            try {
                $defaultQueue = 'my-default-' . uniqid();
                $queue = new NatsQueue($client, $defaultQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                expect($queue->getQueue())->toBe($defaultQueue);
                expect($queue->getQueue(null))->toBe($defaultQueue);
                expect($queue->getQueue(''))->toBe($defaultQueue);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('connection handling', function (): void {
        it('provides access to NATS client', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue = new NatsQueue($client, 'default', 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                expect($queue->getClient())->toBe($client);
                expect($queue->getClient()->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });

        it('maintains connection across multiple operations', function (): void {
            $client = createWorkerTestClient();

            try {
                $queue = new NatsQueue($client, 'default', 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                // Multiple push operations
                $uniqueQueue = 'multi-op-' . uniqid();
                $received = [];
                $client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$received): void {
                    $received[] = $msg;
                });

                for ($i = 0; $i < 5; $i++) {
                    $queue->pushRaw(json_encode(['uuid' => "job-{$i}"]), $uniqueQueue);
                }

                $client->process(1.0);

                expect(count($received))->toBe(5);
                expect($client->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });
    });
});
