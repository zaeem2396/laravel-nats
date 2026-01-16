<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Queue;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;

/**
 * NatsConnector creates NatsQueue instances from configuration.
 *
 * This connector is registered with Laravel's queue manager and is
 * responsible for creating properly configured queue instances.
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

        return new NatsQueue(
            client: $client,
            defaultQueue: $config['queue'] ?? 'default',
            retryAfter: $config['retry_after'] ?? 60,
        );
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
