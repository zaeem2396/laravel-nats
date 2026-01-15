<?php

declare(strict_types=1);

/**
 * ============================================================================
 * CONNECTION INTEGRATION TESTS
 * ============================================================================
 *
 * These tests verify the connection works with a real NATS server.
 * They require a running NATS server (automatically skipped if unavailable).
 *
 * Integration tests validate:
 * - TCP connection establishment
 * - Protocol handshake (INFO/CONNECT)
 * - Authentication (if configured)
 * - Connection lifecycle (connect/disconnect)
 * ============================================================================
 */

use LaravelNats\Core\Connection\Connection;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Exceptions\ConnectionException;

describe('connection lifecycle', function (): void {
    it('connects to NATS server', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        expect($connection->isConnected())->toBeTrue();

        $connection->disconnect();
    });

    it('receives server info on connect', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        $serverInfo = $connection->getServerInfo();

        expect($serverInfo)->not->toBeNull()
            ->and($serverInfo->version)->not->toBeEmpty()
            ->and($serverInfo->port)->toBe(4222);

        $connection->disconnect();
    });

    it('disconnects cleanly', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();
        expect($connection->isConnected())->toBeTrue();

        $connection->disconnect();
        expect($connection->isConnected())->toBeFalse();
    });

    it('handles multiple connect calls', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();
        $connection->connect(); // Should be a no-op

        expect($connection->isConnected())->toBeTrue();

        $connection->disconnect();
    });

    it('clears state on disconnect', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();
        $connection->disconnect();

        expect($connection->getServerInfo())->toBeNull();
    });
});

describe('ping pong', function (): void {
    it('sends ping and receives pong', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();
        $connection->ping();

        // Read the PONG response
        $deadline = microtime(true) + 1.0;
        $gotPong = false;

        while (microtime(true) < $deadline) {
            $line = $connection->readLine();
            if ($line !== null && $connection->getParser()->detectType($line) === 'PONG') {
                $gotPong = true;
                break;
            }
            usleep(1000);
        }

        expect($gotPong)->toBeTrue();

        $connection->disconnect();
    });
});

describe('error handling', function (): void {
    it('throws when writing without connection', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        expect(fn () => $connection->write('test'))
            ->toThrow(ConnectionException::class, 'Not connected');
    });

    it('throws when reading without connection', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        expect(fn () => $connection->read())
            ->toThrow(ConnectionException::class, 'Not connected');
    });

    it('throws when connecting to wrong port', function (): void {
        $config = ConnectionConfig::fromArray([
            'host' => 'localhost',
            'port' => 9999, // Wrong port
            'timeout' => 1.0,
        ]);
        $connection = new Connection($config);

        expect(fn () => $connection->connect())
            ->toThrow(ConnectionException::class);
    });
});
