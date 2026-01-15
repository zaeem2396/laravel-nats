<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Core\Client;

/**
 * Nats Facade provides static access to NATS messaging.
 *
 * Usage:
 *   Nats::publish('orders.created', ['order_id' => 123]);
 *   Nats::subscribe('orders.*', fn($msg) => logger($msg->getPayload()));
 *   $reply = Nats::request('users.get', ['id' => 1]);
 *
 * @method static Client connection(string|null $name = null) Get a connection instance
 * @method static string getDefaultConnection() Get the default connection name
 * @method static void setDefaultConnection(string $name) Set the default connection name
 * @method static array getConnections() Get all active connections
 * @method static void disconnect(string|null $name = null) Disconnect from a connection
 * @method static void disconnectAll() Disconnect from all connections
 * @method static Client reconnect(string|null $name = null) Reconnect to a connection
 * @method static void publish(string $subject, mixed $payload, array $headers = []) Publish a message
 * @method static string subscribe(string $subject, callable $callback, string|null $queueGroup = null) Subscribe to a subject
 * @method static MessageInterface request(string $subject, mixed $payload, float $timeout = 5.0, array $headers = []) Send request and wait for reply
 * @method static void unsubscribe(string $sid, int|null $maxMessages = null) Unsubscribe from a subscription
 * @method static int process(float $timeout = 0.0) Process incoming messages
 * @method static bool isConnected() Check if connected to NATS
 * @method static void ping() Send a ping to the server
 *
 * @see \LaravelNats\Laravel\NatsManager
 * @see \LaravelNats\Core\Client
 */
class Nats extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'nats';
    }
}
