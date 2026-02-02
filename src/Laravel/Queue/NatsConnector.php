<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\JetStream\JetStreamConfig;

/**
 * NatsConnector creates NatsQueue instances from configuration.
 *
 * This connector is registered with Laravel's queue manager and is
 * responsible for creating properly configured queue instances.
 * When delayed jobs are enabled (config queue.delayed.enabled), JetStream
 * is used and the delay stream/consumer are ensured at connect time.
 */
class NatsConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @param array<string, mixed> $config
     *
     * @return Queue
     */
    public function connect(array $config): Queue
    {
        $connectionConfig = $this->createConnectionConfig($config);
        $client = new Client($connectionConfig);
        $client->connect();

        $prefix = $config['prefix'] ?? 'laravel.queue.';
        // Support both 'dead_letter_queue' and 'dlq_subject' for backward compatibility
        $dlqSubject = $config['dead_letter_queue'] ?? $config['dlq_subject'] ?? null;

        // Normalize empty string to null
        if ($dlqSubject === '') {
            $dlqSubject = null;
        }

        // If DLQ is a relative path, prepend the prefix
        if ($dlqSubject !== null && is_string($dlqSubject) && ! str_contains($dlqSubject, '.')) {
            $dlqSubject = $prefix . $dlqSubject;
        }

        $jetStream = null;
        $delayedConfig = null;

        $delayed = $config['delayed'] ?? $this->readConfig('nats.queue.delayed', []);
        $delayedEnabled = is_array($delayed) && ($delayed['enabled'] ?? false);

        if ($delayedEnabled) {
            $jsConfig = JetStreamConfig::fromArray($this->readConfig('nats.jetstream', []));
            $jetStream = $client->getJetStream($jsConfig);
            DelayStreamBootstrap::ensureStreamAndConsumer(
                $jetStream,
                $delayed['stream'] ?? 'laravel_delayed',
                $delayed['subject_prefix'] ?? 'laravel.delayed.',
                $delayed['consumer'] ?? 'laravel_delayed_worker',
            );
            $delayedConfig = [
                'stream' => $delayed['stream'] ?? 'laravel_delayed',
                'subject_prefix' => $delayed['subject_prefix'] ?? 'laravel.delayed.',
                'consumer' => $delayed['consumer'] ?? 'laravel_delayed_worker',
            ];
        }

        return new NatsQueue(
            client: $client,
            defaultQueue: $config['queue'] ?? 'default',
            retryAfter: $config['retry_after'] ?? 60,
            maxTries: $config['tries'] ?? 3,
            deadLetterQueue: $dlqSubject,
            jetStream: $jetStream,
            delayedConfig: $delayedConfig,
        );
    }

    /**
     * Read a config value when Laravel config is available (e.g. when queue is resolved from container).
     *
     * @param string $key Config key (e.g. "nats.queue.delayed")
     * @param mixed $default Default when config unavailable
     *
     * @return mixed
     */
    protected function readConfig(string $key, mixed $default = []): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            $value = config($key, $default);

            return $value ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Create a ConnectionConfig from the queue configuration.
     *
     * @param array<string, mixed> $config
     *
     * @return ConnectionConfig
     */
    protected function createConnectionConfig(array $config): ConnectionConfig
    {
        return new ConnectionConfig(
            host: $config['host'] ?? 'localhost',
            port: (int) ($config['port'] ?? 4222),
            user: $config['user'] ?? null,
            password: $config['password'] ?? null,
            token: $config['token'] ?? null,
            timeout: (float) ($config['timeout'] ?? 5.0),
            pingInterval: (float) ($config['ping_interval'] ?? 120.0),
            maxPingsOut: (int) ($config['max_pings_out'] ?? 2),
            verbose: (bool) ($config['verbose'] ?? false),
            pedantic: (bool) ($config['pedantic'] ?? false),
            clientName: $config['client_name'] ?? 'laravel-queue',
        );
    }
}
