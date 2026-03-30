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

it('parses bracketed IPv6 with port without leaving brackets on host', function (): void {
    $e = NatsServerEndpoint::parse('[::1]:4222');

    expect($e)->not->toBeNull()
        ->and($e->host)->toBe('::1')
        ->and($e->port)->toBe(4222);
});

it('parses bracketed IPv6 default port', function (): void {
    $e = NatsServerEndpoint::parse('[::1]');

    expect($e)->not->toBeNull()
        ->and($e->host)->toBe('::1')
        ->and($e->port)->toBe(4222);
});

it('parses nats url with bracketed IPv6', function (): void {
    $e = NatsServerEndpoint::parse('nats://[::1]:4223');

    expect($e)->not->toBeNull()
        ->and($e->host)->toBe('::1')
        ->and($e->port)->toBe(4223);
});

it('returns null for invalid port token', function (): void {
    expect(NatsServerEndpoint::parse(''))->toBeNull()
        ->and(NatsServerEndpoint::parse('host:notaport'))->toBeNull()
        ->and(NatsServerEndpoint::parse('[::1'))->toBeNull()
        ->and(NatsServerEndpoint::parse('[::1]garbage'))->toBeNull();
});

it('defaults port when host has no colon', function (): void {
    $e = NatsServerEndpoint::parse('localhost');

    expect($e)->not->toBeNull()
        ->and($e->port)->toBe(4222);
});
