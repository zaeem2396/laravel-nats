<?php

declare(strict_types=1);

use LaravelNats\Laravel\Facades\Nats;
use LaravelNats\Laravel\NatsManager;

it('resolves to NatsManager instance', function (): void {
    $resolved = Nats::getFacadeRoot();

    expect($resolved)->toBeInstanceOf(NatsManager::class);
});

it('can get default connection name via facade', function (): void {
    expect(Nats::getDefaultConnection())->toBe('default');
});

it('can set default connection via facade', function (): void {
    Nats::setDefaultConnection('custom');

    expect(Nats::getDefaultConnection())->toBe('custom');
});

it('can get connections via facade', function (): void {
    $connections = Nats::getConnections();

    expect($connections)->toBeArray();
});

it('can disconnect all via facade', function (): void {
    Nats::disconnectAll();

    expect(Nats::getConnections())->toBeEmpty();
});

it('proxies method calls to manager', function (): void {
    // Using a method that doesn't require actual connection
    $name = Nats::getDefaultConnection();

    expect($name)->toBeString();
});

it('has correct facade accessor', function (): void {
    $reflection = new ReflectionClass(Nats::class);
    $method = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    $accessor = $method->invoke(null);

    expect($accessor)->toBe('nats');
});
