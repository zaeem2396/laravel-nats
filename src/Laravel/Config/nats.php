<?php

declare(strict_types=1);

/**
 * ============================================================================
 * NATS CONFIGURATION
 * ============================================================================
 *
 * This file configures the NATS messaging integration for your Laravel app.
 * Publish this file using: php artisan vendor:publish --tag=nats-config
 *
 * Environment variables take precedence over values defined here.
 * ============================================================================
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The default NATS connection to use when no connection is specified.
    | This should match one of the connections defined below.
    |
    */

    'default' => env('NATS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | NATS Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many NATS connections as you need.
    | Each connection can have its own host, port, authentication, etc.
    |
    | Supported authentication:
    | - 'user' + 'password': Username/password authentication
    | - 'token': Token-based authentication
    | - None: No authentication (for local development)
    |
    */

    'connections' => [

        'default' => [
            // Server configuration
            'host' => env('NATS_HOST', 'localhost'),
            'port' => (int) env('NATS_PORT', 4222),

            // Authentication (optional)
            'user' => env('NATS_USER'),
            'password' => env('NATS_PASSWORD'),
            'token' => env('NATS_TOKEN'),

            // Connection settings
            'timeout' => (float) env('NATS_TIMEOUT', 5.0),
            'ping_interval' => (float) env('NATS_PING_INTERVAL', 120.0),
            'max_pings_out' => (int) env('NATS_MAX_PINGS_OUT', 2),

            // TLS/SSL (optional)
            'tls' => [
                'enabled' => (bool) env('NATS_TLS_ENABLED', false),
                'options' => [
                    // 'verify_peer' => true,
                    // 'cafile' => '/path/to/ca.pem',
                ],
            ],

            // Client identification
            'client_name' => env('NATS_CLIENT_NAME', config('app.name', 'laravel') . '-nats'),
            'verbose' => (bool) env('NATS_VERBOSE', false),
            'pedantic' => (bool) env('NATS_PEDANTIC', false),
        ],

        // Example: Secondary connection for different environment
        // 'secondary' => [
        //     'host' => env('NATS_SECONDARY_HOST', 'nats-2.example.com'),
        //     'port' => (int) env('NATS_SECONDARY_PORT', 4222),
        //     'user' => env('NATS_SECONDARY_USER'),
        //     'password' => env('NATS_SECONDARY_PASSWORD'),
        //     'timeout' => 5.0,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    |
    | The default serializer to use for message payloads.
    |
    | Supported: "json", "php"
    |
    */

    'serializer' => env('NATS_SERIALIZER', 'json'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for NATS operations. Useful for debugging.
    |
    */

    'logging' => [
        'enabled' => (bool) env('NATS_LOGGING', false),
        'channel' => env('NATS_LOG_CHANNEL', config('logging.default')),
    ],

];
