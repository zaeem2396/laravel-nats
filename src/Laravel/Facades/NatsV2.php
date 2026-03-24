<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Facades;

use Basis\Nats\Client;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the v2 NATS stack (basis-company/nats).
 *
 * @method static void publish(string $subject, array $payload, array $headers = [], string|null $connection = null)
 * @method static string subscribe(string $subject, callable $handler, string|null $queueGroup = null, string|null $connection = null)
 * @method static void unsubscribe(string $subscriptionId)
 * @method static void unsubscribeAll(string|null $connection = null)
 * @method static mixed process(string|null $connection = null, int|float|null $timeout = 0)
 * @method static Client connection(string|null $name = null)
 * @method static void disconnect(string|null $name = null)
 * @method static void disconnectAll()
 *
 * @see \LaravelNats\Laravel\NatsV2Gateway
 */
class NatsV2 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'nats.v2';
    }
}
