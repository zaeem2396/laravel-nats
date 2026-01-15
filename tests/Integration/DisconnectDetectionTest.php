<?php

declare(strict_types=1);

/**
 * ============================================================================
 * DISCONNECT DETECTION INTEGRATION TESTS
 * ============================================================================
 *
 * These tests verify the proactive disconnect detection mechanisms.
 * They test health checks, idle time tracking, and connection probing.
 *
 * Run with: docker-compose up -d && vendor/bin/pest tests/Integration/DisconnectDetectionTest.php
 * ============================================================================
 */

use LaravelNats\Core\Connection\Connection;

describe('connection health check', function (): void {
    it('passes health check on healthy connection', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        expect($connection->healthCheck())->toBeTrue();
        expect($connection->getFailedPingCount())->toBe(0);

        $connection->disconnect();
    });

    it('tracks idle time correctly', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        // Initially, idle time should be very small
        expect($connection->getIdleTime())->toBeLessThan(1.0);

        // Wait a bit
        usleep(100000); // 100ms

        // Idle time should have increased
        expect($connection->getIdleTime())->toBeGreaterThan(0.09);

        $connection->disconnect();
    });

    it('resets idle time after activity', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        // Wait a bit
        usleep(100000); // 100ms
        $idleBeforePing = $connection->getIdleTime();

        // Ping/pong should reset activity
        $connection->ping();

        // Read the PONG
        $deadline = microtime(true) + 1.0;
        while (microtime(true) < $deadline) {
            $line = $connection->readLine();
            if ($line !== null && $connection->getParser()->detectType($line) === 'PONG') {
                break;
            }
            usleep(1000);
        }

        // Idle time should be reset (less than before)
        expect($connection->getIdleTime())->toBeLessThan($idleBeforePing);

        $connection->disconnect();
    });

    it('reports health check not due immediately after connect', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        // Health check should not be due right after connection
        expect($connection->isHealthCheckDue())->toBeFalse();

        $connection->disconnect();
    });

    it('clears idle time on disconnect', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();
        usleep(50000); // 50ms

        $connection->disconnect();

        expect($connection->getIdleTime())->toBe(0.0);
    });
});

describe('connection probing', function (): void {
    it('probes connection successfully when connected', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        expect($connection->probeConnection())->toBeTrue();

        $connection->disconnect();
    });

    it('returns false when not connected', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        expect($connection->probeConnection())->toBeFalse();
    });

    it('returns false after disconnect', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();
        $connection->disconnect();

        expect($connection->probeConnection())->toBeFalse();
    });
});

describe('readable check', function (): void {
    it('checks socket readability', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        // Should return false (no data waiting) or null if issues
        // This is non-blocking, so it should return quickly
        $readable = $connection->isReadable(0.0);

        expect($readable)->toBeIn([true, false]); // Should not be null

        $connection->disconnect();
    });

    it('returns null when not connected', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        expect($connection->isReadable())->toBeNull();
    });

    it('detects data waiting to be read', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        // Send a PING - server will respond with PONG
        $connection->ping();

        // Give server time to respond
        usleep(10000); // 10ms

        // Now there should be data waiting
        $readable = $connection->isReadable(0.1);

        expect($readable)->toBeTrue();

        // Clean up - read the PONG
        $connection->readLine();

        $connection->disconnect();
    });
});

describe('stream metadata detection', function (): void {
    it('uses stream metadata in isConnected', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();
        expect($connection->isConnected())->toBeTrue();

        $connection->disconnect();
        expect($connection->isConnected())->toBeFalse();
    });

    it('detects connection issues via metadata', function (): void {
        $config = testConfig();
        $connection = new Connection($config);

        $connection->connect();

        // Connection should be healthy
        expect($connection->probeConnection())->toBeTrue();
        expect($connection->isConnected())->toBeTrue();

        // Simulate disconnect by closing
        $connection->disconnect();

        expect($connection->probeConnection())->toBeFalse();
        expect($connection->isConnected())->toBeFalse();
    });
});
