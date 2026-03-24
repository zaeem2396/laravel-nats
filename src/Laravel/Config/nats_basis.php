<?php

declare(strict_types=1);

/**
 * NATS configuration for the v2 stack (basis-company/nats client).
 *
 * @see docs/v2/GUIDE.md
 * @see docs/v2/MIGRATION.md
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default connection name
    |--------------------------------------------------------------------------
    */
    'default' => env('NATS_BASIS_CONNECTION', env('NATS_CONNECTION', 'default')),

    /*
    |--------------------------------------------------------------------------
    | Message envelope schema version (published JSON body)
    |--------------------------------------------------------------------------
    |
    | Published payloads are wrapped as:
    | { "id": "<uuid>", "type": "<subject>", "version": "<this>", "data": { ... } }
    |
    */
    'envelope_version' => env('NATS_ENVELOPE_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Logging (basis-company/nats client)
    |--------------------------------------------------------------------------
    |
    | When enabled, the PSR-3 logger is passed to Basis\Nats\Client (wire-level
    | debug from the dependency). Uses a Laravel log channel name.
    |
    */

    'logging' => [
        'enabled' => filter_var(env('NATS_BASIS_LOGGING', false), FILTER_VALIDATE_BOOL),
        'channel' => env('NATS_BASIS_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriber (v2.1) - Laravel wrapper around Basis\Nats\Client::subscribe
    |--------------------------------------------------------------------------
    |
    | - middleware: list of class names implementing InboundMiddleware (container-resolved)
    | - dispatch_events: fire NatsInboundMessageReceived before your handler
    | - decode_envelope: reserved; use InboundMessage::envelopePayload() when needed
    |
    */
    'subscriber' => [
        'subject_max_length' => (int) env('NATS_SUBJECT_MAX_LENGTH', 512),
        'warn_on_unconventional_subject' => filter_var(env('NATS_SUBJECT_WARN_UNCONVENTIONAL', false), FILTER_VALIDATE_BOOL),
        'decode_envelope' => filter_var(env('NATS_SUBSCRIBER_DECODE_ENVELOPE', false), FILTER_VALIDATE_BOOL),
        'dispatch_events' => filter_var(env('NATS_SUBSCRIBER_DISPATCH_EVENTS', false), FILTER_VALIDATE_BOOL),
        'middleware' => [
            // LaravelNats\Subscriber\Middleware\LogInboundMiddleware::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Each entry maps to Basis\Nats\Configuration constructor options.
    |
    */
    'connections' => [

        'default' => [
            'host' => env('NATS_HOST', '127.0.0.1'),
            'port' => (int) env('NATS_PORT', 4222),
            'timeout' => (float) env('NATS_TIMEOUT', 1.0),
            'pingInterval' => (int) env('NATS_PING_INTERVAL', 2),
            'user' => env('NATS_USER'),
            'pass' => env('NATS_PASS'),
            'token' => env('NATS_TOKEN'),
            'jwt' => env('NATS_JWT'),
            'nkey' => env('NATS_NKEY'),
            'tlsKeyFile' => env('NATS_TLS_KEY'),
            'tlsCertFile' => env('NATS_TLS_CERT'),
            'tlsCaFile' => env('NATS_TLS_CA'),
            'tlsHandshakeFirst' => filter_var(env('NATS_TLS_HANDSHAKE_FIRST', false), FILTER_VALIDATE_BOOL),
            'reconnect' => filter_var(env('NATS_RECONNECT', true), FILTER_VALIDATE_BOOL),
            'verbose' => filter_var(env('NATS_VERBOSE', false), FILTER_VALIDATE_BOOL),
            'pedantic' => filter_var(env('NATS_PEDANTIC', false), FILTER_VALIDATE_BOOL),
            'lang' => env('NATS_CLIENT_LANG', 'php'),
            'version' => env('NATS_CLIENT_VERSION', 'laravel-nats'),
        ],

    ],

];
