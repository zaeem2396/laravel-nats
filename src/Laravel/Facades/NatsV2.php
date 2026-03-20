<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Facades;

use Basis\Nats\Client;
use Illuminate\Support\Facades\Facade;
use LaravelNats\Laravel\NatsV2Gateway;

/**
 * Facade for the v2 NATS stack (basis-company/nats).
 *
 * @method static void publish(string $subject, array $payload, array $headers = [], string|null $connection = null)
 * @method static Client connection(string|null $name = null)
 * @method static void disconnect(string|null $name = null)
 * @method static void disconnectAll()
 *
 * @see NatsV2Gateway
 */
class NatsV2 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'nats.v2';
    }
}
