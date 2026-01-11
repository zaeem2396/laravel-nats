<?php

/**
 * ============================================================================
 * PEST PHP CONFIGURATION
 * ============================================================================
 *
 * This file configures Pest PHP, a testing framework built on top of PHPUnit.
 * Pest provides a cleaner, more expressive syntax for writing tests.
 *
 * Key Concepts:
 * - uses(): Apply traits or base classes to test files
 * - beforeEach(): Run before each test in matching files
 * - afterEach(): Run after each test in matching files
 *
 * Directory Structure:
 * - tests/Unit/: Fast, isolated unit tests
 * - tests/Feature/: Tests with Laravel application context
 * - tests/Integration/: Tests requiring external services (NATS)
 *
 * Running Tests:
 *   vendor/bin/pest              # Run all tests
 *   vendor/bin/pest --unit       # Run only unit tests
 *   vendor/bin/pest --parallel   # Run tests in parallel
 *   vendor/bin/pest --coverage   # Run with code coverage
 * ============================================================================
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case Classes
|--------------------------------------------------------------------------
| The base test case classes provide common functionality for different
| types of tests. Unit tests use a minimal setup, while Feature tests
| may use Laravel's full application context.
*/

// Unit tests: Pure PHP, no framework dependencies
uses(LaravelNats\Tests\TestCase::class)->in('Unit');

// Feature tests: Laravel application context
uses(LaravelNats\Tests\TestCase::class)->in('Feature');

// Integration tests: Require NATS server
uses(LaravelNats\Tests\TestCase::class)
    ->beforeEach(function (): void {
        // Skip integration tests if NATS is not available
        if (! $this->isNatsAvailable()) {
            $this->markTestSkipped('NATS server not available');
        }
    })
    ->in('Integration');

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
| Define custom Pest expectations for common assertions in NATS testing.
*/

expect()->extend('toBeValidSubject', function () {
    $parser = new \LaravelNats\Core\Protocol\Parser();

    expect($parser->isValidSubject($this->value, true))->toBeTrue(
        "Expected '{$this->value}' to be a valid NATS subject",
    );

    return $this;
});

expect()->extend('toBeInvalidSubject', function () {
    $parser = new \LaravelNats\Core\Protocol\Parser();

    expect($parser->isValidSubject($this->value, true))->toBeFalse(
        "Expected '{$this->value}' to be an invalid NATS subject",
    );

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
| Define helper functions available in all tests.
*/

/**
 * Get a fresh connection configuration for testing.
 *
 * @param array<string, mixed> $overrides Configuration overrides
 *
 * @return \LaravelNats\Core\Connection\ConnectionConfig
 */
function testConfig(array $overrides = []): \LaravelNats\Core\Connection\ConnectionConfig
{
    return \LaravelNats\Core\Connection\ConnectionConfig::fromArray(array_merge([
        'host' => env('NATS_HOST', 'localhost'),
        'port' => (int) env('NATS_PORT', 4222),
        'timeout' => 5.0,
    ], $overrides));
}

/**
 * Create a test client connected to NATS.
 *
 * @return \LaravelNats\Core\Client
 */
function testClient(): \LaravelNats\Core\Client
{
    $client = new \LaravelNats\Core\Client(testConfig());
    $client->connect();

    return $client;
}
