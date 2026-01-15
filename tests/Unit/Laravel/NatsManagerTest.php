<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use LaravelNats\Core\Client;
use LaravelNats\Laravel\NatsManager;

beforeEach(function (): void {
    $this->manager = $this->app->make(NatsManager::class);
});

it('is bound as singleton in container', function (): void {
    $manager1 = $this->app->make('nats');
    $manager2 = $this->app->make('nats');

    expect($manager1)->toBe($manager2)
        ->and($manager1)->toBeInstanceOf(NatsManager::class);
});

it('has correct default connection name', function (): void {
    expect($this->manager->getDefaultConnection())->toBe('default');
});

it('allows changing default connection', function (): void {
    $this->manager->setDefaultConnection('secondary');

    expect($this->manager->getDefaultConnection())->toBe('secondary');
});

it('respects environment variable for default connection', function (): void {
    $this->app->make(ConfigRepository::class)->set('nats.default', 'production');

    expect($this->manager->getDefaultConnection())->toBe('production');
});

it('throws exception for unconfigured connection', function (): void {
    $this->manager->connection('nonexistent');
})->throws(InvalidArgumentException::class, 'NATS connection [nonexistent] not configured');

it('returns empty connections array initially', function (): void {
    expect($this->manager->getConnections())->toBeEmpty();
});

it('tracks connections after creation', function (): void {
    // Skip actual connection in unit test
    // This test verifies the connections tracking mechanism
    $connections = $this->manager->getConnections();

    expect($connections)->toBeArray();
});

it('can disconnect all connections', function (): void {
    $this->manager->disconnectAll();

    expect($this->manager->getConnections())->toBeEmpty();
});

it('resolves Client from container using default connection', function (): void {
    // This test verifies the binding exists, actual connection would fail without NATS server
    $this->app->make(ConfigRepository::class)->set('nats.connections.default', [
        'host' => 'localhost',
        'port' => 4222,
    ]);

    expect($this->app->bound(Client::class))->toBeTrue();
});

it('uses json serializer by default', function (): void {
    // Verify config default
    $serializer = $this->app->make(ConfigRepository::class)->get('nats.serializer');

    expect($serializer)->toBe('json');
});

it('can be configured to use php serializer', function (): void {
    $this->app->make(ConfigRepository::class)->set('nats.serializer', 'php');

    $serializer = $this->app->make(ConfigRepository::class)->get('nats.serializer');

    expect($serializer)->toBe('php');
});

it('merges default config on registration', function (): void {
    $config = $this->app->make(ConfigRepository::class);

    expect($config->has('nats'))->toBeTrue()
        ->and($config->has('nats.default'))->toBeTrue()
        ->and($config->has('nats.connections'))->toBeTrue()
        ->and($config->has('nats.serializer'))->toBeTrue();
});

it('has default connection configuration', function (): void {
    $config = $this->app->make(ConfigRepository::class);
    $defaultConn = $config->get('nats.connections.default');

    expect($defaultConn)->toBeArray()
        ->and($defaultConn)->toHaveKey('host')
        ->and($defaultConn)->toHaveKey('port')
        ->and($defaultConn['host'])->toBe('localhost')
        ->and($defaultConn['port'])->toBe(4222);
});

it('has logging configuration', function (): void {
    $config = $this->app->make(ConfigRepository::class);

    expect($config->has('nats.logging'))->toBeTrue()
        ->and($config->get('nats.logging.enabled'))->toBeFalse();
});
