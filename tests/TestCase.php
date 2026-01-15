<?php

declare(strict_types=1);

namespace LaravelNats\Tests;

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
