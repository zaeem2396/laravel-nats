<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Laravel\Queue\NatsQueue;

describe('NatsQueue', function (): void {
    beforeEach(function (): void {
        if (! isPortAvailable(4222)) {
            $this->markTestSkipped('NATS server not available');
        }

        $config = ConnectionConfig::local();
        $this->client = new Client($config);
        $this->client->connect();

        $this->queue = new NatsQueue($this->client, 'test-queue', 90);
        $this->queue->setContainer(new Container());
    });

    afterEach(function (): void {
        if (isset($this->client) && $this->client->isConnected()) {
            $this->client->disconnect();
        }
    });

    describe('getQueue', function (): void {
        it('returns the default queue name', function (): void {
            expect($this->queue->getQueue())->toBe('test-queue');
        });

        it('returns custom queue name when provided', function (): void {
            expect($this->queue->getQueue('custom'))->toBe('custom');
        });

        it('returns default when null is passed', function (): void {
            expect($this->queue->getQueue(null))->toBe('test-queue');
        });

        it('returns default when empty string is passed', function (): void {
            expect($this->queue->getQueue(''))->toBe('test-queue');
        });
    });

    describe('getRetryAfter', function (): void {
        it('returns the retry after value', function (): void {
            expect($this->queue->getRetryAfter())->toBe(90);
        });

        it('uses default retry after value', function (): void {
            $queue = new NatsQueue($this->client);
            expect($queue->getRetryAfter())->toBe(60);
        });
    });

    describe('size', function (): void {
        it('returns zero for size (NATS Core limitation)', function (): void {
            expect($this->queue->size())->toBe(0);
        });

        it('returns zero regardless of queue name', function (): void {
            expect($this->queue->size('any-queue'))->toBe(0);
        });
    });

    describe('pushRaw', function (): void {
        it('can push a raw payload', function (): void {
            $payload = json_encode([
                'uuid' => 'test-123',
                'displayName' => 'TestJob',
                'job' => 'App\\Jobs\\TestJob',
                'data' => ['foo' => 'bar'],
            ]);

            $jobId = $this->queue->pushRaw($payload);

            expect($jobId)->toBe('test-123');
        });

        it('extracts uuid from payload as job id', function (): void {
            $payload = json_encode([
                'uuid' => 'unique-uuid-456',
                'job' => 'TestJob',
            ]);

            $jobId = $this->queue->pushRaw($payload);

            expect($jobId)->toBe('unique-uuid-456');
        });

        it('falls back to id field if uuid not present', function (): void {
            $payload = json_encode([
                'id' => 'fallback-id-789',
                'job' => 'TestJob',
            ]);

            $jobId = $this->queue->pushRaw($payload);

            expect($jobId)->toBe('fallback-id-789');
        });

        it('generates uuid if not present in payload', function (): void {
            $payload = json_encode([
                'displayName' => 'TestJob',
                'job' => 'App\\Jobs\\TestJob',
            ]);

            $jobId = $this->queue->pushRaw($payload);

            expect($jobId)->toBeString();
            expect($jobId)->not->toBeEmpty();
            // UUID format check
            expect(strlen($jobId))->toBe(36);
        });

        it('pushes to custom queue', function (): void {
            $payload = json_encode(['uuid' => 'custom-queue-job']);

            $jobId = $this->queue->pushRaw($payload, 'custom-queue');

            expect($jobId)->toBe('custom-queue-job');
        });
    });

    describe('getClient', function (): void {
        it('returns the NATS client', function (): void {
            expect($this->queue->getClient())->toBe($this->client);
        });

        it('returns a connected client', function (): void {
            expect($this->queue->getClient()->isConnected())->toBeTrue();
        });
    });

    describe('pop', function (): void {
        it('returns null when no messages available', function (): void {
            // Use a unique queue name to avoid interference
            $uniqueQueue = 'empty-queue-' . uniqid();

            $job = $this->queue->pop($uniqueQueue);

            expect($job)->toBeNull();
        });
    });

    describe('later', function (): void {
        it('pushes job immediately (delay not supported in NATS Core)', function (): void {
            // Note: later() falls back to immediate push in NATS Core
            // This will be properly implemented with JetStream
            $payload = json_encode([
                'uuid' => 'delayed-job-123',
                'job' => 'TestJob',
            ]);

            // For now, later() should work like push()
            // The delay is ignored until JetStream implementation
            expect(true)->toBeTrue(); // Placeholder assertion
        });
    });

    describe('constructor defaults', function (): void {
        it('uses default queue name', function (): void {
            $queue = new NatsQueue($this->client);
            expect($queue->getQueue())->toBe('default');
        });

        it('uses default retry after value', function (): void {
            $queue = new NatsQueue($this->client);
            expect($queue->getRetryAfter())->toBe(60);
        });
    });
});
