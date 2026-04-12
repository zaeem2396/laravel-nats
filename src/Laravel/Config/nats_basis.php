<?php

declare(strict_types=1);

/**
 * NATS configuration for the v2 stack (basis-company/nats client).
 *
 * @see docs/v2/GUIDE.md
 * @see docs/v2/MIGRATION.md
 * @see docs/v2/SECURITY.md
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
    | Correlation / Request-ID headers (NatsV2 publish)
    |--------------------------------------------------------------------------
    |
    | When inject_on_publish is true, NatsPublisher merges these from the current
    | HTTP request (if any) before sending HPUB. See docs/v2/CORRELATION.md.
    |
    */
    'correlation' => [
        'inject_on_publish' => filter_var(env('NATS_CORRELATION_INJECT', false), FILTER_VALIDATE_BOOL),
        'request_id_header' => env('NATS_REQUEST_ID_HEADER', 'X-Request-Id'),
        'correlation_id_header' => env('NATS_CORRELATION_ID_HEADER', 'Nats-Correlation-Id'),
        'generate_when_missing' => filter_var(env('NATS_CORRELATION_GENERATE_REQUEST_ID', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability (v2.5) — metrics hooks, envelope redaction, health ping
    |--------------------------------------------------------------------------
    |
    | Metrics: rebind NatsMetricsContract to forward to Prometheus or OpenTelemetry. Labels use
    | low-cardinality values only (connection name, outcome).
    | Redaction: substring match against array keys when logging envelope data (EnvelopeDataRedactor).
    |
    */
    'observability' => [
        'metrics_enabled' => filter_var(env('NATS_OBSERVABILITY_METRICS', false), FILTER_VALIDATE_BOOL),
        'publish_latency_histogram' => filter_var(env('NATS_OBSERVABILITY_PUBLISH_LATENCY_MS', false), FILTER_VALIDATE_BOOL),
        'redact_key_substrings' => array_values(array_filter(array_map(
            static fn (string $s): string => trim($s),
            explode(',', (string) env('NATS_REDACT_KEY_SUBSTRINGS', 'password,secret,token,authorization')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency (v2.4) — subscriber middleware + publish envelope/header
    |--------------------------------------------------------------------------
    |
    | Publishers may set `idempotency_key` on the payload (lifted into the JSON
    | envelope and mirrored as an HPUB header). Register
    | IdempotencyInboundMiddleware in nats_basis.subscriber.middleware and use a
    | shared cache store (e.g. Redis) for multi-worker consumers.
    |
    */
    'idempotency' => [
        'enabled' => filter_var(env('NATS_IDEMPOTENCY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'store' => env('NATS_IDEMPOTENCY_STORE', 'cache'),
        'cache_store' => env('NATS_IDEMPOTENCY_CACHE_STORE'),
        'cache_key_prefix' => env('NATS_IDEMPOTENCY_CACHE_PREFIX', 'nats:idempotency:'),
        'ttl_seconds' => (int) env('NATS_IDEMPOTENCY_TTL', 86400),
        'header_name' => env('NATS_IDEMPOTENCY_HEADER', 'Nats-Idempotency-Key'),
    ],

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
            // LaravelNats\Subscriber\Middleware\CorrelationLogInboundMiddleware::class,
            // LaravelNats\Subscriber\Middleware\RedactedEnvelopeLogInboundMiddleware::class,
            // LaravelNats\Subscriber\Middleware\IdempotencyInboundMiddleware::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue driver (`nats_basis` connection driver)
    |--------------------------------------------------------------------------
    |
    | Used when `config/queue.php` sets `'driver' => 'nats_basis'`. Jobs use the
    | same JSON payload shape as the legacy `nats` driver for `queue:work`.
    | DLQ subject follows legacy rules (prefix + name when name has no dots).
    |
    | @see docs/v2/QUEUE.md
    |
    */
    'queue' => [
        'prefix' => env('NATS_BASIS_QUEUE_PREFIX', 'laravel.queue.'),
        'retry_after' => (int) env('NATS_BASIS_QUEUE_RETRY_AFTER', 60),
        'tries' => (int) env('NATS_BASIS_QUEUE_TRIES', 3),
        'block_for' => (float) env('NATS_BASIS_QUEUE_BLOCK_FOR', 0.1),
        'max_in_flight' => ($m = env('NATS_BASIS_QUEUE_MAX_IN_FLIGHT')) !== null && $m !== '' ? (int) $m : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | JetStream (basis client, NatsV2)
    |--------------------------------------------------------------------------
    |
    | Pull defaults for PullConsumerBatch. Presets are optional named stream
    | definitions for BasisStreamProvisioner::provision() - see docs/v2/JETSTREAM.md.
    |
    */
    'jetstream' => [
        'pull' => [
            'default_batch' => (int) env('NATS_V2_JS_PULL_BATCH', 10),
            'default_expires' => (float) env('NATS_V2_JS_PULL_EXPIRES', 0.5),
        ],
        'presets' => [
            'example_events' => [
                'name' => 'EXAMPLE_EVENTS',
                'subjects' => ['example.events.>'],
                'storage' => 'file',
                'retention' => 'limits',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & validation (v2.6)
    |--------------------------------------------------------------------------
    |
    | validate_on_boot: fail fast on bad host/port/timeout (and optional TLS rules in production).
    | tls.require_in_production: when APP_ENV=production, each connection must set TLS material
    | or tlsHandshakeFirst. See docs/v2/SECURITY.md.
    | Artisan: `php artisan nats:v2:config:validate` runs the same validator with force=true.
    |
    */
    'security' => [
        'validate_on_boot' => filter_var(env('NATS_BASIS_VALIDATE_CONFIG', false), FILTER_VALIDATE_BOOL),
        'tls' => [
            'require_in_production' => filter_var(env('NATS_TLS_REQUIRE_IN_PRODUCTION', false), FILTER_VALIDATE_BOOL),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subject ACL (v2.6) — optional client-side allowlists
    |--------------------------------------------------------------------------
    |
    | Not a substitute for NATS server authorization. When enabled, publish/subscribe subjects
    | must match allowed_publish_prefixes / allowed_subscribe_prefixes (prefix or exact; trailing
    | dot means prefix). Empty list when enabled denies all. See docs/v2/SECURITY.md.
    | Env keys: NATS_ACL_ENABLED, NATS_ACL_PUBLISH_PREFIXES, NATS_ACL_SUBSCRIBE_PREFIXES (comma-separated lists).
    |
    */
    'acl' => [
        'enabled' => filter_var(env('NATS_ACL_ENABLED', false), FILTER_VALIDATE_BOOL),
        'allowed_publish_prefixes' => array_values(array_filter(array_map(
            static fn (string $s): string => trim($s),
            explode(',', (string) env('NATS_ACL_PUBLISH_PREFIXES', '')),
        ))),
        'allowed_subscribe_prefixes' => array_values(array_filter(array_map(
            static fn (string $s): string => trim($s),
            explode(',', (string) env('NATS_ACL_SUBSCRIBE_PREFIXES', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Each entry maps to Basis\Nats\Configuration constructor options.
    |
    | NatsBasisConfigurationValidator checks host/port/timeout (and TLS rules when enabled).
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

            /*
            | Optional seed peers for bootstrap failover ("host:port" or URL), comma-separated via env.
            | merge_info_connect_urls: after a successful connect, merge INFO connect_urls into the pool (issues one ping).
            */
            'servers' => array_values(array_filter(array_map(
                static fn (string $s): string => trim($s),
                explode(',', (string) env('NATS_BASIS_SERVERS', '')),
            ))),
            'merge_info_connect_urls' => filter_var(env('NATS_MERGE_INFO_CONNECT_URLS', false), FILTER_VALIDATE_BOOL),
        ],

    ],

];
