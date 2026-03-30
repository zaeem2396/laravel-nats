<?php

declare(strict_types=1);

use LaravelNats\Connection\NatsServerEndpoint;

it('parses host:port', function (): void {
    $e = NatsServerEndpoint::parse('10.0.0.1:4333');

    expect($e)->not->toBeNull()
        ->and($e->host)->toBe('10.0.0.1')
        ->and($e->port)->toBe(4333);
});

it('parses nats url', function (): void {
    $e = NatsServerEndpoint::parse('nats://example.com:4223');

    expect($e)->not->toBeNull()
        ->and($e->host)->toBe('example.com')
        ->and($e->port)->toBe(4223);
});

