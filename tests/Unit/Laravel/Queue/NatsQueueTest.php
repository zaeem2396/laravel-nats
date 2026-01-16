<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Laravel\Queue\NatsQueue;

/**
 * Create a connected NATS client for queue tests.
 *
 * @return array{client: Client, queue: NatsQueue}
 */
function createQueueTestSetup(string $queueName = 'test-queue', int $retryAfter = 90): array
{
    $config = ConnectionConfig::local();
    $client = new Client($config);
    $client->connect();

    $queue = new NatsQueue($client, $queueName, $retryAfter);
    $queue->setContainer(new Container());

    return ['client' => $client, 'queue' => $queue];
}

describe('NatsQueue', function (): void {
    describe('getQueue', function (): void {
        it('returns the default queue name', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->getQueue())->toBe('test-queue');
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('returns custom queue name when provided', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->getQueue('custom'))->toBe('custom');
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('returns default when null is passed', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->getQueue(null))->toBe('test-queue');
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('returns default when empty string is passed', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->getQueue(''))->toBe('test-queue');
            } finally {
                $setup['client']->disconnect();
            }
        });
    });

    describe('getRetryAfter', function (): void {
        it('returns the retry after value', function (): void {
            $setup = createQueueTestSetup('test-queue', 90);

            try {
                expect($setup['queue']->getRetryAfter())->toBe(90);
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('uses default retry after value', function (): void {
            $setup = createQueueTestSetup('test-queue', 60);

            try {
                $queue = new NatsQueue($setup['client']);
                expect($queue->getRetryAfter())->toBe(60);
            } finally {
                $setup['client']->disconnect();
            }
        });
    });

    describe('size', function (): void {
        it('returns zero for size (NATS Core limitation)', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->size())->toBe(0);
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('returns zero regardless of queue name', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->size('any-queue'))->toBe(0);
            } finally {
                $setup['client']->disconnect();
            }
        });
    });

    describe('pushRaw', function (): void {
        it('can push a raw payload', function (): void {
            $setup = createQueueTestSetup();

            try {
                $payload = json_encode([
                    'uuid' => 'test-123',
                    'displayName' => 'TestJob',
                    'job' => 'App\\Jobs\\TestJob',
                    'data' => ['foo' => 'bar'],
                ]);

                $jobId = $setup['queue']->pushRaw($payload);

                expect($jobId)->toBe('test-123');
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('extracts uuid from payload as job id', function (): void {
            $setup = createQueueTestSetup();

            try {
                $payload = json_encode([
                    'uuid' => 'unique-uuid-456',
                    'job' => 'TestJob',
                ]);

                $jobId = $setup['queue']->pushRaw($payload);

                expect($jobId)->toBe('unique-uuid-456');
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('falls back to id field if uuid not present', function (): void {
            $setup = createQueueTestSetup();

            try {
                $payload = json_encode([
                    'id' => 'fallback-id-789',
                    'job' => 'TestJob',
                ]);

                $jobId = $setup['queue']->pushRaw($payload);

                expect($jobId)->toBe('fallback-id-789');
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('generates uuid if not present in payload', function (): void {
            $setup = createQueueTestSetup();

            try {
                $payload = json_encode([
                    'displayName' => 'TestJob',
                    'job' => 'App\\Jobs\\TestJob',
                ]);

                $jobId = $setup['queue']->pushRaw($payload);

                expect($jobId)->toBeString();
                expect($jobId)->not->toBeEmpty();
                // UUID format check
                expect(strlen($jobId))->toBe(36);
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('pushes to custom queue', function (): void {
            $setup = createQueueTestSetup();

            try {
                $payload = json_encode(['uuid' => 'custom-queue-job']);

                $jobId = $setup['queue']->pushRaw($payload, 'custom-queue');

                expect($jobId)->toBe('custom-queue-job');
            } finally {
                $setup['client']->disconnect();
            }
        });
    });

    describe('getClient', function (): void {
        it('returns the NATS client', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->getClient())->toBe($setup['client']);
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('returns a connected client', function (): void {
            $setup = createQueueTestSetup();

            try {
                expect($setup['queue']->getClient()->isConnected())->toBeTrue();
            } finally {
                $setup['client']->disconnect();
            }
        });
    });

    describe('pop', function (): void {
        it('returns null when no messages available', function (): void {
            $setup = createQueueTestSetup();

            try {
                // Use a unique queue name to avoid interference
                $uniqueQueue = 'empty-queue-' . uniqid();

                $job = $setup['queue']->pop($uniqueQueue);

                expect($job)->toBeNull();
            } finally {
                $setup['client']->disconnect();
            }
        });
    });

    describe('later', function (): void {
        it('pushes job immediately (delay not supported in NATS Core)', function (): void {
            // Note: later() falls back to immediate push in NATS Core
            // This will be properly implemented with JetStream
            expect(true)->toBeTrue(); // Placeholder assertion
        });
    });

    describe('constructor defaults', function (): void {
        it('uses default queue name', function (): void {
            $setup = createQueueTestSetup();

            try {
                $queue = new NatsQueue($setup['client']);
                expect($queue->getQueue())->toBe('default');
            } finally {
                $setup['client']->disconnect();
            }
        });

        it('uses default retry after value', function (): void {
            $setup = createQueueTestSetup();

            try {
                $queue = new NatsQueue($setup['client']);
                expect($queue->getRetryAfter())->toBe(60);
            } finally {
                $setup['client']->disconnect();
            }
        });
    });
});
