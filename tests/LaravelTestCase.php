<?php

declare(strict_types=1);

namespace LaravelNats\Tests;

use LaravelNats\Laravel\Facades\Nats;
use LaravelNats\Laravel\Providers\NatsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Base test case for Laravel integration tests.
 *
 * Uses Orchestra Testbench to provide a Laravel application environment
 * for testing the NATS service provider, facade, and manager.
 */
abstract class LaravelTestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NatsServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Nats' => Nats::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        // Set default config for testing
        $app['config']->set('nats.connections.default', [
            'host' => env('NATS_HOST', 'localhost'),
            'port' => (int) env('NATS_PORT', 4222),
            'timeout' => 5.0,
        ]);
    }

    /**
     * Check if NATS server is available.
     */
    protected function isNatsAvailable(): bool
    {
        $socket = @fsockopen(
            env('NATS_HOST', 'localhost'),
            (int) env('NATS_PORT', 4222),
            $errno,
            $errstr,
            1,
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
