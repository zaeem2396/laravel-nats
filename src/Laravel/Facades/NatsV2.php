<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Facades;

use Basis\Nats\Client;
use Basis\Nats\Message\Msg;
use Basis\Nats\Stream\Stream;
use Illuminate\Support\Facades\Facade;
use LaravelNats\JetStream\BasisJetStreamManager;

/**
 * Facade for the v2 NATS stack (basis-company/nats).
 *
 * @method static void publish(string $subject, array $payload, array $headers = [], string|null $connection = null)
 * @method static string subscribe(string $subject, callable $handler, string|null $queueGroup = null, string|null $connection = null)
 * @method static void unsubscribe(string $subscriptionId)
 * @method static void unsubscribeAll(string|null $connection = null)
 * @method static mixed process(string|null $connection = null, int|float|null $timeout = 0)
 * @method static Client connection(string|null $name = null)
 * @method static bool ping(string|null $connection = null)
 * @method static void disconnect(string|null $name = null)
 * @method static void disconnectAll()
 * @method static BasisJetStreamManager jetstream(string|null $connection = null)
 * @method static void jetStreamPublish(string $stream, string $subject, array $payload, bool $useEnvelope = true, bool $waitForAck = true, array $headers = [], string|null $connection = null)
 * @method static list<Msg> jetStreamPull(string $stream, string $consumer, int|null $batch = null, float|null $expiresSeconds = null, string|null $connection = null)
 * @method static Stream jetStreamProvisionPreset(string $presetKey, bool $createIfNotExists = true, string|null $connection = null)
 * @method static \Basis\Nats\Message\Payload request(string $subject, mixed $payload, float $timeoutSeconds = 5.0, string|null $connection = null)
 * @method static void drainConnection(float $maxProcessSeconds = 2.0, string|null $connection = null)
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
