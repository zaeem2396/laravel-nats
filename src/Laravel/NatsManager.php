<?php

declare(strict_types=1);

namespace LaravelNats\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Contracts\Serialization\SerializerInterface;
use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\Serialization\JsonSerializer;
use LaravelNats\Core\Serialization\PhpSerializer;

/**
 * NatsManager handles multiple NATS connections for Laravel.
 *
 * This class follows the Manager pattern used by Laravel's DatabaseManager,
 * CacheManager, etc. It manages connection lifecycle and provides a
 * fluent interface for NATS operations.
 *
 * Usage:
 *   $manager->connection('default')->publish('subject', 'payload');
 *   $manager->publish('subject', 'payload'); // Uses default connection
 */
class NatsManager
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The configuration repository.
     */
    protected ConfigRepository $config;

    /**
     * The active connection instances.
     *
     * @var array<string, Client>
     */
    protected array $connections = [];

    /**
     * Create a new NATS manager instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app->make(ConfigRepository::class);
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param string $method Method name
     * @param array<mixed> $parameters Method parameters
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }

    /**
     * Get a NATS connection instance.
     *
     * @param string|null $name Connection name (null for default)
     *
     * @throws InvalidArgumentException If connection doesn't exist
     *
     * @return Client
     */
    public function connection(?string $name = null): Client
    {
        $name ??= $this->getDefaultConnection();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection(): string
    {
        return $this->config->get('nats.default', 'default');
    }

    /**
     * Set the default connection name.
     *
     * @param string $name Connection name
     */
    public function setDefaultConnection(string $name): void
    {
        $this->config->set('nats.default', $name);
    }

    /**
     * Get all connection instances.
     *
     * @return array<string, Client>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Disconnect from a connection.
     *
     * @param string|null $name Connection name (null for default)
     */
    public function disconnect(?string $name = null): void
    {
        $name ??= $this->getDefaultConnection();

        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }

    /**
     * Disconnect from all connections.
     */
    public function disconnectAll(): void
    {
        foreach ($this->connections as $name => $connection) {
            $connection->disconnect();
        }

        $this->connections = [];
    }

    /**
     * Reconnect to a connection.
     *
     * @param string|null $name Connection name (null for default)
     *
     * @return Client
     */
    public function reconnect(?string $name = null): Client
    {
        $this->disconnect($name);

        return $this->connection($name);
    }

    /**
     * Publish a message to a subject.
     *
     * @param string $subject The subject to publish to
     * @param mixed $payload The message payload
     * @param array<string, string> $headers Optional headers
     */
    public function publish(string $subject, mixed $payload, array $headers = []): void
    {
        $this->connection()->publish($subject, $payload, $headers);
    }

    /**
     * Subscribe to a subject.
     *
     * @param string $subject The subject to subscribe to
     * @param callable(MessageInterface): void $callback Message handler
     * @param string|null $queueGroup Optional queue group for load balancing
     *
     * @return string Subscription ID
     */
    public function subscribe(string $subject, callable $callback, ?string $queueGroup = null): string
    {
        if ($queueGroup !== null) {
            return $this->connection()->queueSubscribe($subject, $queueGroup, $callback);
        }

        return $this->connection()->subscribe($subject, $callback);
    }

    /**
     * Send a request and wait for a reply.
     *
     * @param string $subject The subject to request
     * @param mixed $payload The request payload
     * @param float $timeout Timeout in seconds
     * @param array<string, string> $headers Optional headers
     *
     * @return MessageInterface The reply message
     */
    public function request(string $subject, mixed $payload, float $timeout = 5.0, array $headers = []): MessageInterface
    {
        return $this->connection()->request($subject, $payload, $timeout, $headers);
    }

    /**
     * Unsubscribe from a subscription.
     *
     * @param string $sid Subscription ID
     * @param int|null $maxMessages Auto-unsubscribe after N messages
     */
    public function unsubscribe(string $sid, ?int $maxMessages = null): void
    {
        $this->connection()->unsubscribe($sid, $maxMessages);
    }

    /**
     * Process incoming messages.
     *
     * @param float $timeout How long to wait for messages
     *
     * @return int Number of messages processed
     */
    public function process(float $timeout = 0.0): int
    {
        return $this->connection()->process($timeout);
    }

    /**
     * Create a new connection instance.
     *
     * @param string $name Connection name
     *
     * @throws InvalidArgumentException If connection config doesn't exist
     *
     * @return Client
     */
    protected function makeConnection(string $name): Client
    {
        $config = $this->getConnectionConfig($name);
        $connectionConfig = ConnectionConfig::fromArray($config);
        $serializer = $this->makeSerializer();

        $client = new Client($connectionConfig, $serializer);
        $client->connect();

        return $client;
    }

    /**
     * Get the configuration for a connection.
     *
     * @param string $name Connection name
     *
     * @throws InvalidArgumentException If connection doesn't exist
     *
     * @return array<string, mixed>
     */
    protected function getConnectionConfig(string $name): array
    {
        /** @var array<string, array<string, mixed>> $connections */
        $connections = $this->config->get('nats.connections', []);

        if (! isset($connections[$name])) {
            throw new InvalidArgumentException(
                "NATS connection [{$name}] not configured. Check config/nats.php.",
            );
        }

        return $connections[$name];
    }

    /**
     * Create the serializer instance.
     *
     * @return SerializerInterface
     */
    protected function makeSerializer(): SerializerInterface
    {
        $type = $this->config->get('nats.serializer', 'json');

        return match ($type) {
            'php' => new PhpSerializer(),
            default => new JsonSerializer(),
        };
    }
}
