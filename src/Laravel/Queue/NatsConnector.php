<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\JetStream\JetStreamConfig;
use LaravelNats\Support\MixedTypes;

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
            $jsConfig = JetStreamConfig::fromArray(MixedTypes::assoc($this->readConfig('nats.jetstream', [])));
            $jetStream = $client->getJetStream($jsConfig);
            DelayStreamBootstrap::ensureStreamAndConsumer(
                $jetStream,
                MixedTypes::string($delayed['stream'] ?? 'laravel_delayed', 'laravel_delayed'),
                MixedTypes::string($delayed['subject_prefix'] ?? 'laravel.delayed.', 'laravel.delayed.'),
                MixedTypes::string($delayed['consumer'] ?? 'laravel_delayed_worker', 'laravel_delayed_worker'),
            );
            $delayedConfig = [
                'stream' => MixedTypes::string($delayed['stream'] ?? 'laravel_delayed', 'laravel_delayed'),
                'subject_prefix' => MixedTypes::string($delayed['subject_prefix'] ?? 'laravel.delayed.', 'laravel.delayed.'),
                'consumer' => MixedTypes::string($delayed['consumer'] ?? 'laravel_delayed_worker', 'laravel_delayed_worker'),
            ];
        }

        return new NatsQueue(
            client: $client,
            defaultQueue: MixedTypes::string($config['queue'] ?? 'default', 'default'),
            retryAfter: MixedTypes::int($config['retry_after'] ?? 60, 60),
            maxTries: MixedTypes::int($config['tries'] ?? 3, 3),
            deadLetterQueue: MixedTypes::nullableString($dlqSubject),
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
            host: MixedTypes::string($config['host'] ?? 'localhost', 'localhost'),
            port: MixedTypes::int($config['port'] ?? 4222, 4222),
            user: MixedTypes::nullableString($config['user'] ?? null),
            password: MixedTypes::nullableString($config['password'] ?? null),
            token: MixedTypes::nullableString($config['token'] ?? null),
            timeout: MixedTypes::float($config['timeout'] ?? 5.0, 5.0),
            pingInterval: MixedTypes::float($config['ping_interval'] ?? 120.0, 120.0),
            maxPingsOut: MixedTypes::int($config['max_pings_out'] ?? 2, 2),
            verbose: MixedTypes::bool($config['verbose'] ?? false),
            pedantic: MixedTypes::bool($config['pedantic'] ?? false),
            clientName: MixedTypes::string($config['client_name'] ?? 'laravel-queue', 'laravel-queue'),
        );
    }
}
