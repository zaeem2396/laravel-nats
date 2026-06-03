<?php

declare(strict_types=1);

use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Exceptions\NatsException;
use LaravelNats\Laravel\Queue\DelayStreamBootstrap;

it('throws when JetStream is unavailable', function (): void {
    $js = new JetStreamClient(new Client(ConnectionConfig::local()));
    $available = new ReflectionProperty(JetStreamClient::class, 'available');
    $available->setAccessible(true);
    $available->setValue($js, false);

    expect(fn () => DelayStreamBootstrap::ensureStreamAndConsumer(
        $js,
        'DELAY',
        'laravel.delayed.',
        'delay-consumer',
    ))->toThrow(NatsException::class, 'JetStream is not available');
});
