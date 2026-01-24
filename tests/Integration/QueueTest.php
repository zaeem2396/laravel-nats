<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Laravel\Queue\NatsConnector;
use LaravelNats\Laravel\Queue\NatsJob;
use LaravelNats\Laravel\Queue\NatsQueue;

/**
 * Helper to create a connected client for queue tests.
 */
function createQueueTestClient(): Client
{
    $config = ConnectionConfig::local();
    $client = new Client($config);
    $client->connect();

    return $client;
}

describe('Queue Integration', function (): void {
    describe('publish and consume', function (): void {
        it('can publish a job and receive it', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueSubject = 'integration-' . uniqid();
                $queue = new NatsQueue($client, $uniqueSubject, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'integration-test-job',
                    'displayName' => 'TestJob',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                    'data' => ['message' => 'Hello, NATS Queue!'],
                ]);

                // Subscribe first, then publish
                $received = null;
                $client->subscribe('laravel.queue.' . $uniqueSubject, function ($msg) use (&$received): void {
                    $received = $msg;
                });

                // Push the job
                $jobId = $queue->pushRaw($payload);
                expect($jobId)->toBe('integration-test-job');

                // Process to receive
                $client->process(0.5);

                expect($received)->not->toBeNull();
                expect($received->getPayload())->toBe($payload);
            } finally {
                $client->disconnect();
            }
        });

        it('generates unique job ids for each push', function (): void {
            $client = createQueueTestClient();

            try {
                $queue = new NatsQueue($client, 'integration-test', 60);
                $queue->setContainer(new Container());

                $payload1 = json_encode(['job' => 'Job1']);
                $payload2 = json_encode(['job' => 'Job2']);

                $jobId1 = $queue->pushRaw($payload1);
                $jobId2 = $queue->pushRaw($payload2);

                expect($jobId1)->not->toBe($jobId2);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('NatsConnector integration', function (): void {
        it('creates a working queue connection', function (): void {
            $connector = new NatsConnector();
            $config = [
                'host' => 'localhost',
                'port' => 4222,
                'queue' => 'connector-test',
                'retry_after' => 90,
            ];

            $queue = $connector->connect($config);

            try {
                expect($queue)->toBeInstanceOf(NatsQueue::class);
                expect($queue->getClient()->isConnected())->toBeTrue();
                expect($queue->getQueue())->toBe('connector-test');
                expect($queue->getRetryAfter())->toBe(90);

                // Test that we can push
                $payload = json_encode(['uuid' => 'connector-job', 'test' => true]);
                $jobId = $queue->pushRaw($payload);
                expect($jobId)->toBe('connector-job');
            } finally {
                $queue->getClient()->disconnect();
            }
        });

        it('works with authenticated NATS server', function (): void {
            if (! isPortAvailable(4223)) {
                $this->markTestSkipped('Secured NATS server not available');
            }

            $connector = new NatsConnector();
            $config = [
                'host' => 'localhost',
                'port' => 4223,
                'user' => 'testuser',
                'password' => 'testpass',
                'queue' => 'secured-queue',
            ];

            $queue = $connector->connect($config);

            try {
                expect($queue)->toBeInstanceOf(NatsQueue::class);
                expect($queue->getClient()->isConnected())->toBeTrue();

                // Push a job to verify it works
                $payload = json_encode(['uuid' => 'secured-job']);
                $jobId = $queue->pushRaw($payload);
                expect($jobId)->toBe('secured-job');
            } finally {
                $queue->getClient()->disconnect();
            }
        });
    });

    describe('NatsJob integration', function (): void {
        it('creates a job from queue pop simulation', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueQueue = 'job-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'popped-job-123',
                    'displayName' => 'App\\Jobs\\ProcessOrder',
                    'attempts' => 1,
                    'data' => ['order_id' => 456],
                ]);

                // Create a NatsJob directly (simulating what pop() would do)
                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                expect($job->getJobId())->toBe('popped-job-123');
                expect($job->attempts())->toBe(1);
                expect($job->getQueue())->toBe($uniqueQueue);
                expect($job->payload()['data']['order_id'])->toBe(456);
            } finally {
                $client->disconnect();
            }
        });

        it('can release and re-queue a job', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueQueue = 'release-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'release-job',
                    'attempts' => 1,
                ]);

                // Expected payload after release (attempts incremented)
                $expectedPayload = json_encode([
                    'uuid' => 'release-job',
                    'attempts' => 2,
                ]);

                // Track released jobs
                $releasedPayload = null;
                $client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$releasedPayload): void {
                    $releasedPayload = $msg->getPayload();
                });

                // Create and release the job
                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                $job->release();

                // Process to receive the released job
                $client->process(0.5);

                expect($job->isReleased())->toBeTrue();
                // After release, attempts should be incremented
                expect($releasedPayload)->toBe($expectedPayload);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('multiple queues', function (): void {
        it('can push to different queues', function (): void {
            $client = createQueueTestClient();

            try {
                $queue = new NatsQueue($client, 'multi-queue-test', 60);
                $queue->setContainer(new Container());

                $queue1Messages = [];
                $queue2Messages = [];

                $client->subscribe('laravel.queue.queue-one', function ($msg) use (&$queue1Messages): void {
                    $queue1Messages[] = $msg->getPayload();
                });

                $client->subscribe('laravel.queue.queue-two', function ($msg) use (&$queue2Messages): void {
                    $queue2Messages[] = $msg->getPayload();
                });

                // Push to different queues
                $queue->pushRaw(json_encode(['uuid' => 'job-1', 'queue' => 'one']), 'queue-one');
                $queue->pushRaw(json_encode(['uuid' => 'job-2', 'queue' => 'two']), 'queue-two');
                $queue->pushRaw(json_encode(['uuid' => 'job-3', 'queue' => 'one']), 'queue-one');

                // Process
                $client->process(0.5);

                expect($queue1Messages)->toHaveCount(2);
                expect($queue2Messages)->toHaveCount(1);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('queue subject naming', function (): void {
        it('uses correct subject prefix', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueQueue = 'subject-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());

                $receivedSubject = null;
                $client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$receivedSubject): void {
                    $receivedSubject = $msg->getSubject();
                });

                $queue->pushRaw(json_encode(['uuid' => 'subject-job']));
                $client->process(0.3);

                expect($receivedSubject)->toBe('laravel.queue.' . $uniqueQueue);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('high volume', function (): void {
        it('can handle multiple rapid pushes', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueQueue = 'volume-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());

                $received = [];
                $client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$received): void {
                    $received[] = json_decode($msg->getPayload(), true);
                });

                // Push 50 jobs rapidly
                $jobCount = 50;
                for ($i = 0; $i < $jobCount; $i++) {
                    $queue->pushRaw(json_encode([
                        'uuid' => "job-{$i}",
                        'index' => $i,
                    ]));
                }

                // Process to receive all
                $client->process(2.0);

                expect(count($received))->toBe($jobCount);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('failed jobs', function (): void {
        it('marks job as failed when fail() is called', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueQueue = 'failed-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'failed-job-test',
                    'displayName' => 'TestJob',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                    'data' => ['message' => 'This will fail'],
                ]);

                // Create job directly from payload (simulating received job)
                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                expect($job->hasFailed())->toBeFalse();

                // Fail the job
                $exception = new RuntimeException('Test failure');
                $job->fail($exception);

                expect($job->hasFailed())->toBeTrue();
                expect($job->getFailureException())->toBe($exception);
            } finally {
                $client->disconnect();
            }
        });

        it('routes failed job to DLQ when configured', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueQueue = 'dlq-test-' . uniqid();
                $dlqSubject = 'laravel.queue.dlq-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60, 3, $dlqSubject);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'dlq-job-test',
                    'displayName' => 'TestJob',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                    'data' => ['message' => 'This will go to DLQ'],
                ]);

                // Subscribe to DLQ first
                $dlqMessage = null;
                $client->subscribe($dlqSubject, function ($msg) use (&$dlqMessage): void {
                    $dlqMessage = $msg;
                });

                // Create job directly and fail it
                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                // Fail the job
                $exception = new RuntimeException('DLQ test failure');
                $job->fail($exception);

                // Process to receive DLQ message
                $client->process(0.5);

                expect($dlqMessage)->not->toBeNull();
                $dlqPayload = json_decode($dlqMessage->getPayload(), true);
                expect($dlqPayload['uuid'])->toBe('dlq-job-test');
                expect($dlqPayload['original_queue'])->toBe($uniqueQueue);
                expect($dlqPayload['failure_message'])->toContain('DLQ test failure');
            } finally {
                $client->disconnect();
            }
        });

        it('does not route to DLQ when not configured', function (): void {
            $client = createQueueTestClient();

            try {
                $uniqueQueue = 'no-dlq-test-' . uniqid();
                $queue = new NatsQueue($client, $uniqueQueue, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                expect($queue->getDeadLetterQueueSubject())->toBeNull();

                $payload = json_encode([
                    'uuid' => 'no-dlq-job',
                    'displayName' => 'TestJob',
                ]);

                // Create job directly
                $job = new NatsJob(
                    container: new Container(),
                    nats: $queue,
                    job: $payload,
                    connectionName: 'nats',
                    queue: $uniqueQueue,
                );

                // Fail the job - should not throw or route to DLQ
                $job->fail(new RuntimeException('Test'));

                expect($job->hasFailed())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });
    });
});
