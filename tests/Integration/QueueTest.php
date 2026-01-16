<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Laravel\Queue\NatsConnector;
use LaravelNats\Laravel\Queue\NatsJob;
use LaravelNats\Laravel\Queue\NatsQueue;

describe('Queue Integration', function (): void {
    beforeEach(function (): void {
        // Ensure NATS is available before trying to connect
        if (! isPortAvailable(4222)) {
            $this->markTestSkipped('NATS server not available on port 4222');
        }

        $config = ConnectionConfig::local();
        $this->client = new Client($config);
        $this->client->connect();

        $this->queue = new NatsQueue($this->client, 'integration-test', 60);
        $this->queue->setContainer(new Container());
        $this->queue->setConnectionName('nats');
    });

    afterEach(function (): void {
        if (isset($this->client) && $this->client !== null && $this->client->isConnected()) {
            $this->client->disconnect();
        }
    });

    describe('publish and consume', function (): void {
        it('can publish a job and receive it', function (): void {
            $uniqueSubject = 'integration-' . uniqid();
            $queue = new NatsQueue($this->client, $uniqueSubject, 60);
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
            $this->client->subscribe('laravel.queue.' . $uniqueSubject, function ($msg) use (&$received): void {
                $received = $msg;
            });

            // Push the job
            $jobId = $queue->pushRaw($payload);
            expect($jobId)->toBe('integration-test-job');

            // Process to receive
            $this->client->process(0.5);

            expect($received)->not->toBeNull();
            expect($received->getPayload())->toBe($payload);
        });

        it('generates unique job ids for each push', function (): void {
            $payload1 = json_encode(['job' => 'Job1']);
            $payload2 = json_encode(['job' => 'Job2']);

            $jobId1 = $this->queue->pushRaw($payload1);
            $jobId2 = $this->queue->pushRaw($payload2);

            expect($jobId1)->not->toBe($jobId2);
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

            expect($queue)->toBeInstanceOf(NatsQueue::class);
            expect($queue->getClient()->isConnected())->toBeTrue();
            expect($queue->getQueue())->toBe('connector-test');
            expect($queue->getRetryAfter())->toBe(90);

            // Test that we can push
            $payload = json_encode(['uuid' => 'connector-job', 'test' => true]);
            $jobId = $queue->pushRaw($payload);
            expect($jobId)->toBe('connector-job');

            // Clean up
            $queue->getClient()->disconnect();
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

            expect($queue)->toBeInstanceOf(NatsQueue::class);
            expect($queue->getClient()->isConnected())->toBeTrue();

            // Push a job to verify it works
            $payload = json_encode(['uuid' => 'secured-job']);
            $jobId = $queue->pushRaw($payload);
            expect($jobId)->toBe('secured-job');

            // Clean up
            $queue->getClient()->disconnect();
        });
    });

    describe('NatsJob integration', function (): void {
        it('creates a job from queue pop simulation', function (): void {
            $uniqueQueue = 'job-test-' . uniqid();
            $queue = new NatsQueue($this->client, $uniqueQueue, 60);
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
        });

        it('can release and re-queue a job', function (): void {
            $uniqueQueue = 'release-test-' . uniqid();
            $queue = new NatsQueue($this->client, $uniqueQueue, 60);
            $queue->setContainer(new Container());
            $queue->setConnectionName('nats');

            $payload = json_encode([
                'uuid' => 'release-job',
                'attempts' => 1,
            ]);

            // Track released jobs
            $releasedPayload = null;
            $this->client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$releasedPayload): void {
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
            $this->client->process(0.5);

            expect($job->isReleased())->toBeTrue();
            expect($releasedPayload)->toBe($payload);
        });
    });

    describe('multiple queues', function (): void {
        it('can push to different queues', function (): void {
            $queue1Messages = [];
            $queue2Messages = [];

            $this->client->subscribe('laravel.queue.queue-one', function ($msg) use (&$queue1Messages): void {
                $queue1Messages[] = $msg->getPayload();
            });

            $this->client->subscribe('laravel.queue.queue-two', function ($msg) use (&$queue2Messages): void {
                $queue2Messages[] = $msg->getPayload();
            });

            // Push to different queues
            $this->queue->pushRaw(json_encode(['uuid' => 'job-1', 'queue' => 'one']), 'queue-one');
            $this->queue->pushRaw(json_encode(['uuid' => 'job-2', 'queue' => 'two']), 'queue-two');
            $this->queue->pushRaw(json_encode(['uuid' => 'job-3', 'queue' => 'one']), 'queue-one');

            // Process
            $this->client->process(0.5);

            expect($queue1Messages)->toHaveCount(2);
            expect($queue2Messages)->toHaveCount(1);
        });
    });

    describe('queue subject naming', function (): void {
        it('uses correct subject prefix', function (): void {
            $uniqueQueue = 'subject-test-' . uniqid();
            $queue = new NatsQueue($this->client, $uniqueQueue, 60);
            $queue->setContainer(new Container());

            $receivedSubject = null;
            $this->client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$receivedSubject): void {
                $receivedSubject = $msg->getSubject();
            });

            $queue->pushRaw(json_encode(['uuid' => 'subject-job']));
            $this->client->process(0.3);

            expect($receivedSubject)->toBe('laravel.queue.' . $uniqueQueue);
        });
    });

    describe('high volume', function (): void {
        it('can handle multiple rapid pushes', function (): void {
            $uniqueQueue = 'volume-test-' . uniqid();
            $queue = new NatsQueue($this->client, $uniqueQueue, 60);
            $queue->setContainer(new Container());

            $received = [];
            $this->client->subscribe('laravel.queue.' . $uniqueQueue, function ($msg) use (&$received): void {
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
            $this->client->process(2.0);

            expect(count($received))->toBe($jobCount);
        });
    });
});
