<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Laravel\Queue\NatsQueue;

/**
 * Stability Tests
 *
 * These tests verify long-running stability and reliability of the NATS client.
 * They are designed to catch issues that only manifest under sustained use.
 */

beforeEach(function (): void {
    // Skip if NATS server is not available
    $socket = @fsockopen('localhost', 4222, $errno, $errstr, 1);
    if ($socket === false) {
        $this->markTestSkipped('NATS server not available');
    }
    fclose($socket);
});

/**
 * Helper to create a connected client for stability tests.
 */
function createStabilityClient(): Client
{
    $config = ConnectionConfig::local();
    $client = new Client($config);
    $client->connect();

    return $client;
}

describe('Long-running Stability', function (): void {

    describe('connection stability', function (): void {

        it('maintains connection over multiple operations', function (): void {
            $client = createStabilityClient();

            try {
                $operationCount = 50;
                $successCount = 0;

                for ($i = 0; $i < $operationCount; $i++) {
                    expect($client->isConnected())->toBeTrue();

                    // Perform a simple publish to verify connection
                    $client->publish('stability.heartbeat', ['beat' => $i]);
                    $successCount++;
                }

                expect($successCount)->toBe($operationCount);
                expect($client->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });

        it('handles rapid publish operations without failure', function (): void {
            $client = createStabilityClient();

            try {
                $subject = 'stability.rapid-publish.' . uniqid();
                $messageCount = 100;

                for ($i = 0; $i < $messageCount; $i++) {
                    $client->publish($subject, ['index' => $i, 'data' => str_repeat('x', 100)]);
                }

                // Verify connection is still healthy
                expect($client->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });

        it('handles publish and subscribe cycles', function (): void {
            $client = createStabilityClient();

            try {
                $cycles = 10;
                $messagesPerCycle = 5;

                for ($cycle = 0; $cycle < $cycles; $cycle++) {
                    $subject = 'stability.cycle.' . uniqid();
                    $received = [];

                    // Subscribe first
                    $sid = $client->subscribe($subject, function ($msg) use (&$received): void {
                        $received[] = $msg->getDecodedPayload();
                    });

                    // Then publish
                    for ($i = 0; $i < $messagesPerCycle; $i++) {
                        $client->publish($subject, ['cycle' => $cycle, 'msg' => $i]);
                    }

                    $client->process(0.5);
                    $client->unsubscribe($sid);

                    expect(count($received))->toBe($messagesPerCycle);
                }

                expect($client->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });

        it('handles reconnection after disconnect', function (): void {
            $config = ConnectionConfig::local();
            $client = new Client($config);

            try {
                // First connection
                $client->connect();
                expect($client->isConnected())->toBeTrue();

                // Verify functionality on first connection
                $client->publish('stability.first', ['test' => true]);
                $client->disconnect();
                expect($client->isConnected())->toBeFalse();

                // Reconnect
                $client->connect();
                expect($client->isConnected())->toBeTrue();

                // Verify functionality after reconnect
                $subject = 'stability.reconnect.' . uniqid();
                $received = null;

                $client->subscribe($subject, function ($msg) use (&$received): void {
                    $received = $msg->getDecodedPayload();
                });

                $client->publish($subject, ['after' => 'reconnect']);
                $client->process(0.5);

                expect($received)->toBe(['after' => 'reconnect']);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('queue stability', function (): void {

        it('handles sustained queue push operations', function (): void {
            $client = createStabilityClient();

            try {
                $queueName = 'stability-queue-' . uniqid();
                $queue = new NatsQueue($client, $queueName, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $jobCount = 20;
                $pushedJobs = [];

                // Push multiple jobs
                for ($i = 0; $i < $jobCount; $i++) {
                    $payload = json_encode([
                        'uuid' => 'stability-job-' . $i,
                        'displayName' => 'StabilityJob',
                        'data' => ['index' => $i],
                        'attempts' => 1,
                    ]);

                    $jobId = $queue->pushRaw($payload, $queueName);
                    $pushedJobs[] = $jobId;
                }

                expect(count($pushedJobs))->toBe($jobCount);
                expect(count(array_unique($pushedJobs)))->toBe($jobCount);
                expect($client->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });

        it('handles push and immediate pop', function (): void {
            $client = createStabilityClient();

            try {
                $queueName = 'stability-push-pop-' . uniqid();
                $queue = new NatsQueue($client, $queueName, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $payload = json_encode([
                    'uuid' => 'push-pop-job',
                    'displayName' => 'PushPopJob',
                    'attempts' => 1,
                ]);

                // Subscribe first, then push
                $receivedPayload = null;
                $fullSubject = 'laravel.queue.' . $queueName;
                $client->subscribe($fullSubject, function ($msg) use (&$receivedPayload): void {
                    $receivedPayload = $msg->getPayload();
                });

                $jobId = $queue->pushRaw($payload, $queueName);
                expect($jobId)->toBe('push-pop-job');

                $client->process(0.5);

                expect($receivedPayload)->toBe($payload);
                expect($client->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });

        it('handles job payload validation', function (): void {
            $client = createStabilityClient();

            try {
                $queueName = 'stability-payload-' . uniqid();
                $queue = new NatsQueue($client, $queueName, 60);
                $queue->setContainer(new Container());
                $queue->setConnectionName('nats');

                $largeData = str_repeat('x', 10000);
                $payload = json_encode([
                    'uuid' => 'large-job',
                    'displayName' => 'LargeJob',
                    'data' => ['large' => $largeData],
                    'attempts' => 1,
                ]);

                // Subscribe first
                $receivedPayload = null;
                $fullSubject = 'laravel.queue.' . $queueName;
                $client->subscribe($fullSubject, function ($msg) use (&$receivedPayload): void {
                    $receivedPayload = $msg->getPayload();
                });

                $jobId = $queue->pushRaw($payload, $queueName);

                $client->process(0.5);

                expect($receivedPayload)->toBe($payload);
                $decoded = json_decode((string) $receivedPayload, true);
                expect($decoded['data']['large'])->toBe($largeData);
            } finally {
                $client->disconnect();
            }
        });
    });

    describe('request reply stability', function (): void {

        it('handles multiple request-reply cycles', function (): void {
            $client = createStabilityClient();

            try {
                $cycles = 5;

                for ($i = 0; $i < $cycles; $i++) {
                    $subject = 'stability.request.' . uniqid();

                    // Set up responder
                    $client->subscribe($subject, function ($msg) use ($client): void {
                        if ($msg->expectsReply()) {
                            $request = $msg->getDecodedPayload();
                            $client->publish(
                                $msg->getReplyTo(),
                                ['echo' => $request['message'] ?? 'no-message'],
                            );
                        }
                    });

                    // Send request
                    $response = $client->request($subject, ['message' => 'cycle-' . $i], 2.0);

                    expect($response)->not->toBeNull();
                    expect($response->getDecodedPayload())->toBe(['echo' => 'cycle-' . $i]);
                }

                expect($client->isConnected())->toBeTrue();
            } finally {
                $client->disconnect();
            }
        });
    });
});

describe('Memory Stability', function (): void {

    it('does not leak memory during publish operations', function (): void {
        $client = createStabilityClient();

        try {
            $subject = 'memory.publish.' . uniqid();
            $iterations = 100;

            // Warm up
            for ($i = 0; $i < 10; $i++) {
                $client->publish($subject, ['warmup' => $i]);
            }

            $initialMemory = memory_get_usage(true);

            for ($i = 0; $i < $iterations; $i++) {
                $client->publish($subject, [
                    'index' => $i,
                    'data' => str_repeat('x', 1000),
                ]);
            }

            $finalMemory = memory_get_usage(true);
            $memoryGrowth = $finalMemory - $initialMemory;

            // Allow up to 2MB growth (generous for test environment)
            expect($memoryGrowth)->toBeLessThan(2 * 1024 * 1024);
        } finally {
            $client->disconnect();
        }
    });

    it('does not leak memory during subscribe cycles', function (): void {
        $client = createStabilityClient();

        try {
            $iterations = 20;

            // Warm up
            for ($i = 0; $i < 5; $i++) {
                $subject = 'memory.warmup.' . uniqid();
                $sid = $client->subscribe($subject, function (): void {});
                $client->unsubscribe($sid);
            }

            $initialMemory = memory_get_usage(true);

            for ($i = 0; $i < $iterations; $i++) {
                $subject = 'memory.subscribe.' . uniqid();
                $received = [];

                $sid = $client->subscribe($subject, function ($msg) use (&$received): void {
                    $received[] = $msg;
                });

                $client->publish($subject, ['test' => $i]);
                $client->process(0.1);
                $client->unsubscribe($sid);

                // Clear references
                $received = [];
            }

            $finalMemory = memory_get_usage(true);
            $memoryGrowth = $finalMemory - $initialMemory;

            // Allow up to 2MB growth
            expect($memoryGrowth)->toBeLessThan(2 * 1024 * 1024);
        } finally {
            $client->disconnect();
        }
    });

    it('does not leak memory during queue push operations', function (): void {
        $client = createStabilityClient();

        try {
            $queueName = 'memory-queue-' . uniqid();
            $queue = new NatsQueue($client, $queueName, 60);
            $queue->setContainer(new Container());
            $queue->setConnectionName('nats');

            $iterations = 30;

            // Warm up
            for ($i = 0; $i < 5; $i++) {
                $payload = json_encode(['warmup' => $i]);
                $queue->pushRaw($payload, $queueName);
            }

            $initialMemory = memory_get_usage(true);

            for ($i = 0; $i < $iterations; $i++) {
                $payload = json_encode([
                    'uuid' => 'memory-job-' . $i,
                    'displayName' => 'MemoryJob',
                    'data' => str_repeat('x', 500),
                    'attempts' => 1,
                ]);

                $queue->pushRaw($payload, $queueName);
            }

            $finalMemory = memory_get_usage(true);
            $memoryGrowth = $finalMemory - $initialMemory;

            // Allow up to 2MB growth
            expect($memoryGrowth)->toBeLessThan(2 * 1024 * 1024);
        } finally {
            $client->disconnect();
        }
    });
});
