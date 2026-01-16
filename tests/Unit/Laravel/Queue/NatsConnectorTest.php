<?php

declare(strict_types=1);

use LaravelNats\Laravel\Queue\NatsConnector;
use LaravelNats\Laravel\Queue\NatsQueue;

describe('NatsConnector', function (): void {
    describe('connect', function (): void {
        it('creates a NatsQueue instance from config', function (): void {
            // Skip if no NATS server available
            if (! isPortAvailable(4222)) {
                $this->markTestSkipped('NATS server not available');
            }

            $connector = new NatsConnector();
            $config = [
                'host' => 'localhost',
                'port' => 4222,
                'queue' => 'test-queue',
                'retry_after' => 120,
            ];

            $queue = $connector->connect($config);

            expect($queue)->toBeInstanceOf(NatsQueue::class);
            expect($queue->getQueue())->toBe('test-queue');
            expect($queue->getRetryAfter())->toBe(120);

            // Clean up
            $queue->getClient()->disconnect();
        });

        it('uses default values when config is minimal', function (): void {
            if (! isPortAvailable(4222)) {
                $this->markTestSkipped('NATS server not available');
            }

            $connector = new NatsConnector();
            $queue = $connector->connect([]);

            expect($queue)->toBeInstanceOf(NatsQueue::class);
            expect($queue->getQueue())->toBe('default');
            expect($queue->getRetryAfter())->toBe(60);

            // Clean up
            $queue->getClient()->disconnect();
        });

        it('applies authentication config correctly', function (): void {
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

            // Clean up
            $queue->getClient()->disconnect();
        });

        it('applies token authentication correctly', function (): void {
            if (! isPortAvailable(4224)) {
                $this->markTestSkipped('Token NATS server not available');
            }

            $connector = new NatsConnector();
            $config = [
                'host' => 'localhost',
                'port' => 4224,
                'token' => 'secret-token-12345',
            ];

            $queue = $connector->connect($config);

            expect($queue)->toBeInstanceOf(NatsQueue::class);
            expect($queue->getClient()->isConnected())->toBeTrue();

            // Clean up
            $queue->getClient()->disconnect();
        });

        it('applies timeout configuration', function (): void {
            if (! isPortAvailable(4222)) {
                $this->markTestSkipped('NATS server not available');
            }

            $connector = new NatsConnector();
            $config = [
                'timeout' => 10.0,
                'ping_interval' => 60.0,
                'max_pings_out' => 5,
            ];

            $queue = $connector->connect($config);

            expect($queue)->toBeInstanceOf(NatsQueue::class);

            // Clean up
            $queue->getClient()->disconnect();
        });

        it('applies client name configuration', function (): void {
            if (! isPortAvailable(4222)) {
                $this->markTestSkipped('NATS server not available');
            }

            $connector = new NatsConnector();
            $config = [
                'client_name' => 'my-custom-queue-client',
            ];

            $queue = $connector->connect($config);

            expect($queue)->toBeInstanceOf(NatsQueue::class);

            // Clean up
            $queue->getClient()->disconnect();
        });
    });
});
