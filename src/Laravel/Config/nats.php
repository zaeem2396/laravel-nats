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

    /*
    |--------------------------------------------------------------------------
    | JetStream Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for NATS JetStream (persistence and streaming).
    | JetStream must be enabled on your NATS server for these features.
    |
    | 'domain': JetStream domain for multi-tenancy (optional)
    |           When set, API subjects use: $JS.<domain>.API.*
    |           When null, uses default: $JS.API.*
    |
    | 'timeout': Default timeout for JetStream API requests (seconds)
    |
    */

    'jetstream' => [
        'domain' => env('NATS_JETSTREAM_DOMAIN'),
        'timeout' => (float) env('NATS_JETSTREAM_TIMEOUT', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for using NATS as a Laravel queue driver.
    | Add this to your config/queue.php 'connections' array:
    |
    | 'nats' => [
    |     'driver' => 'nats',
    |     'host' => env('NATS_HOST', 'localhost'),
    |     'port' => env('NATS_PORT', 4222),
    |     'queue' => env('NATS_QUEUE', 'default'),
    |     'retry_after' => 60,
    | ],
    |
    */

    'queue' => [
        // Default queue name
        'queue' => env('NATS_QUEUE', 'default'),

        // Subject prefix for queue messages
        'prefix' => env('NATS_QUEUE_PREFIX', 'laravel.queue.'),

        // Seconds to wait before retrying a failed job
        'retry_after' => (int) env('NATS_QUEUE_RETRY_AFTER', 60),

        // Block timeout when waiting for jobs (seconds)
        'block_for' => (int) env('NATS_QUEUE_BLOCK_FOR', 0),

        // Dead Letter Queue (DLQ) subject for failed jobs
        // Set to null to disable DLQ routing
        // If set to a simple name (e.g., 'failed'), it will be prefixed automatically
        // If set to a full subject (e.g., 'laravel.queue.failed'), it will be used as-is
        'dead_letter_queue' => env('NATS_QUEUE_DLQ', null),

        // Delayed jobs (requires JetStream)
        // Set 'enabled' => true in your queue connection to use JetStream for later()
        'delayed' => [
            'enabled' => (bool) env('NATS_QUEUE_DELAYED_ENABLED', false),
            'stream' => env('NATS_QUEUE_DELAYED_STREAM', 'laravel_delayed'),
            'subject_prefix' => env('NATS_QUEUE_DELAYED_SUBJECT_PREFIX', 'laravel.delayed.'),
            'consumer' => env('NATS_QUEUE_DELAYED_CONSUMER', 'laravel_delayed_worker'),
        ],
    ],

];
