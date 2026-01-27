<?php

declare(strict_types=1);

namespace LaravelNats\Tests;

use LaravelNats\Exceptions\ConnectionException;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for all package tests.
 *
 * This class provides common functionality and helper methods
 * used across unit, feature, and integration tests.
 *
 * For Laravel-specific tests (Feature), you may extend the
 * Orchestra Testbench TestCase instead.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * NATS server host for integration tests.
     */
    protected string $natsHost;

    /**
     * NATS server port for integration tests.
     */
    protected int $natsPort;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->natsHost = getenv('NATS_HOST') ?: 'localhost';
        $this->natsPort = (int) (getenv('NATS_PORT') ?: 4222);
    }

    /**
     * Create a connected NATS client for JetStream tests.
     *
     * Uses env NATS_HOST/NATS_PORT (same as isNatsAvailable), retries the full
     * connection up to 3 times, waits for JetStream, and performs one warmup
     * round-trip so the returned client is ready for use. This reduces flaky
     * "Not connected to NATS server" failures in CI and long test runs.
     *
     * @throws \RuntimeException If connection fails or JetStream is unavailable after retries
     *
     * @return \LaravelNats\Core\Client Connected and verified client
     */
    public static function createConnectedJetStreamClient(): \LaravelNats\Core\Client
    {
        $host = getenv('NATS_HOST') ?: 'localhost';
        $port = (int) (getenv('NATS_PORT') ?: '4222');
        $config = \LaravelNats\Core\Connection\ConnectionConfig::fromArray([
            'host' => $host,
            'port' => $port,
            'timeout' => 5.0,
        ]);

        $lastException = null;
        $maxConnectionAttempts = 3;

        for ($attempt = 1; $attempt <= $maxConnectionAttempts; $attempt++) {
            try {
                $client = new \LaravelNats\Core\Client($config);
                $client->connect();

                // Wait for connection and JetStream to be ready (up to ~2.5s)
                $waitAttempts = 25;
                $waitAttempt = 0;
                while ($waitAttempt < $waitAttempts) {
                    if ($client->isConnected()) {
                        $serverInfo = $client->getServerInfo();
                        if ($serverInfo !== null && $serverInfo->jetStreamEnabled) {
                            break;
                        }
                    }
                    usleep(100000); // 100ms
                    $waitAttempt++;
                }

                if (! $client->isConnected()) {
                    $client->disconnect();

                    throw new \RuntimeException('Failed to establish NATS connection');
                }

                $serverInfo = $client->getServerInfo();
                if ($serverInfo === null) {
                    $client->disconnect();

                    throw new \RuntimeException('Failed to get ServerInfo after connection');
                }

                if (! $serverInfo->jetStreamEnabled) {
                    $client->disconnect();

                    throw new \RuntimeException('JetStream is not available on the NATS server');
                }

                // Warmup: one JetStream round-trip so the connection is actually usable.
                // STREAM.INFO on a non-existent stream returns an error but confirms connectivity.
                try {
                    $client->request('$JS.API.STREAM.INFO._warmup_', '{}', 2.0);
                } catch (\Throwable $e) {
                    if ($e instanceof ConnectionException) {
                        $client->disconnect();
                        $lastException = $e;
                        if ($attempt < $maxConnectionAttempts) {
                            usleep(200000); // 200ms before retry
                        }
                        continue;
                    }

                    // Non-connection error during warmup (e.g. stream not found) still means round-trip worked.
                    return $client;
                }

                return $client;
            } catch (\RuntimeException $e) {
                $lastException = $e;
                if ($attempt < $maxConnectionAttempts) {
                    usleep(200000);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Check if NATS is reachable (for use in Pest/beforeEach where $this type may not be resolved).
     *
     * @return bool True if NATS host:port is reachable
     */
    public static function isNatsReachable(): bool
    {
        $host = getenv('NATS_HOST') ?: 'localhost';
        $port = (int) (getenv('NATS_PORT') ?: '4222');
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);

        if ($socket !== false) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * Check if a NATS server is available.
     *
     * This method attempts a TCP connection to the NATS server
     * to determine if integration tests can run.
     *
     * @return bool True if NATS is reachable
     */
    protected function isNatsAvailable(): bool
    {
        $socket = @fsockopen($this->natsHost, $this->natsPort, $errno, $errstr, 1);

        if ($socket !== false) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * Get a unique subject name for testing.
     *
     * Using unique subjects prevents test pollution when running
     * tests in parallel or when tests don't clean up properly.
     *
     * @param string $prefix Optional prefix for the subject
     *
     * @return string A unique subject name
     */
    protected function uniqueSubject(string $prefix = 'test'): string
    {
        return sprintf('%s.%s.%s', $prefix, bin2hex(random_bytes(4)), time());
    }

    /**
     * Wait for a condition to be true.
     *
     * Useful for async operations where we need to wait
     * for a message to be received.
     *
     * @param callable(): bool $condition Condition to check
     * @param float $timeout Maximum time to wait in seconds
     * @param int $interval Check interval in microseconds
     *
     * @return bool True if condition was met, false on timeout
     */
    protected function waitFor(callable $condition, float $timeout = 5.0, int $interval = 10000): bool
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            if ($condition()) {
                return true;
            }

            usleep($interval);
        }

        return false;
    }
}
