<?php

declare(strict_types=1);

/**
 * ============================================================================
 * AUTHENTICATION INTEGRATION TESTS
 * ============================================================================
 *
 * These tests verify authentication works with secured NATS servers.
 * They require running NATS servers with authentication enabled.
 *
 * Docker containers:
 * - nats-secured (port 4223): Username/password authentication
 * - nats-token (port 4224): Token-based authentication
 *
 * Run with: docker-compose up -d && vendor/bin/pest tests/Integration/AuthenticationTest.php
 * ============================================================================
 */

use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\Connection;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Exceptions\ConnectionException;

describe('username/password authentication', function (): void {
    beforeEach(function (): void {
        if (! isPortAvailable(4223)) {
            $this->markTestSkipped('Secured NATS server (port 4223) not available');
        }
    });

    it('connects with valid username and password', function (): void {
        $config = securedConfig();
        $connection = new Connection($config);

        $connection->connect();

        expect($connection->isConnected())->toBeTrue();
        expect($connection->getServerInfo())->not->toBeNull();
        expect($connection->getServerInfo()->authRequired)->toBeTrue();

        $connection->disconnect();
    });

    it('rejects connection with wrong username', function (): void {
        $config = securedConfig([
            'user' => 'wronguser',
            'password' => 'testpass',
        ]);
        $connection = new Connection($config);

        expect(fn () => $connection->connect())
            ->toThrow(ConnectionException::class);
    });

    it('rejects connection with wrong password', function (): void {
        $config = securedConfig([
            'user' => 'testuser',
            'password' => 'wrongpassword',
        ]);
        $connection = new Connection($config);

        expect(fn () => $connection->connect())
            ->toThrow(ConnectionException::class);
    });

    it('rejects connection without credentials', function (): void {
        $config = ConnectionConfig::fromArray([
            'host' => 'localhost',
            'port' => 4223,
            'timeout' => 2.0,
            // No user/password provided
        ]);
        $connection = new Connection($config);

        expect(fn () => $connection->connect())
            ->toThrow(ConnectionException::class);
    });

    it('can publish and subscribe with valid auth', function (): void {
        $config = securedConfig();
        $client = new Client($config);
        $client->connect();

        $subject = 'auth.test.' . bin2hex(random_bytes(4));
        $received = null;

        $client->subscribe($subject, function ($message) use (&$received): void {
            $received = $message->getPayload();
        });

        $client->publish($subject, 'authenticated message');

        // Process for message
        $deadline = microtime(true) + 2.0;
        while ($received === null && microtime(true) < $deadline) {
            $client->process(0.1);
        }

        expect($received)->toBe('authenticated message');

        $client->disconnect();
    });
});

describe('token authentication', function (): void {
    beforeEach(function (): void {
        if (! isPortAvailable(4224)) {
            $this->markTestSkipped('Token auth NATS server (port 4224) not available');
        }
    });

    it('connects with valid token', function (): void {
        $config = tokenConfig();
        $connection = new Connection($config);

        $connection->connect();

        expect($connection->isConnected())->toBeTrue();
        expect($connection->getServerInfo())->not->toBeNull();
        expect($connection->getServerInfo()->authRequired)->toBeTrue();

        $connection->disconnect();
    });

    it('rejects connection with wrong token', function (): void {
        $config = tokenConfig([
            'token' => 'wrong-token',
        ]);
        $connection = new Connection($config);

        expect(fn () => $connection->connect())
            ->toThrow(ConnectionException::class);
    });

    it('rejects connection without token', function (): void {
        $config = ConnectionConfig::fromArray([
            'host' => 'localhost',
            'port' => 4224,
            'timeout' => 2.0,
            // No token provided
        ]);
        $connection = new Connection($config);

        expect(fn () => $connection->connect())
            ->toThrow(ConnectionException::class);
    });

    it('can publish and subscribe with valid token', function (): void {
        $config = tokenConfig();
        $client = new Client($config);
        $client->connect();

        $subject = 'token.test.' . bin2hex(random_bytes(4));
        $received = null;

        $client->subscribe($subject, function ($message) use (&$received): void {
            $received = $message->getPayload();
        });

        $client->publish($subject, 'token authenticated');

        // Process for message
        $deadline = microtime(true) + 2.0;
        while ($received === null && microtime(true) < $deadline) {
            $client->process(0.1);
        }

        expect($received)->toBe('token authenticated');

        $client->disconnect();
    });
});

describe('authentication edge cases', function (): void {
    beforeEach(function (): void {
        if (! isPortAvailable(4223)) {
            $this->markTestSkipped('Secured NATS server (port 4223) not available');
        }
    });

    it('handles special characters in password', function (): void {
        // This test validates our JSON encoding handles special chars
        // The actual auth will fail since we're not setting up a user with this password
        // but it validates we don't crash on encoding
        $config = securedConfig([
            'password' => 'test"pass\'with\\special<chars>',
        ]);

        // Should throw auth error, not encoding error
        expect(fn () => (new Connection($config))->connect())
            ->toThrow(ConnectionException::class);
    });

    it('handles empty password correctly', function (): void {
        $config = securedConfig([
            'password' => '',
        ]);

        expect(fn () => (new Connection($config))->connect())
            ->toThrow(ConnectionException::class);
    });

    it('prefers username/password over token when both provided', function (): void {
        // When both are provided, our implementation uses username/password
        // This connects to the user/pass server with valid credentials
        $config = ConnectionConfig::fromArray([
            'host' => 'localhost',
            'port' => 4223,
            'user' => 'testuser',
            'password' => 'testpass',
            'token' => 'ignored-token',
            'timeout' => 2.0,
        ]);

        $connection = new Connection($config);
        $connection->connect();

        expect($connection->isConnected())->toBeTrue();

        $connection->disconnect();
    });
});
