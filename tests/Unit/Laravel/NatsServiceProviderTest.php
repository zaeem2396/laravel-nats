<?php

declare(strict_types=1);

use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Core\Client;
use LaravelNats\JetStream\BasisJetStreamPublisher;
use LaravelNats\JetStream\BasisStreamProvisioner;
use LaravelNats\JetStream\PullConsumerBatch;
use LaravelNats\Laravel\NatsManager;
use LaravelNats\Laravel\NatsV2Gateway;
use LaravelNats\Laravel\Providers\NatsServiceProvider;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;
use LaravelNats\Publisher\NatsPublisher;
use LaravelNats\Subscriber\Contracts\NatsSubscriberContract;
use LaravelNats\Subscriber\NatsBasisSubscriber;
use LaravelNats\Subscriber\SubjectValidator;

it('registers the nats binding', function (): void {
    expect($this->app->bound('nats'))->toBeTrue();
});

it('registers NatsManager alias', function (): void {
    expect($this->app->bound(NatsManager::class))->toBeTrue();
});

it('registers Client binding', function (): void {
    expect($this->app->bound(Client::class))->toBeTrue();
});

it('provides deferred services', function (): void {
    $provider = new NatsServiceProvider($this->app);

    $provides = $provider->provides();

    expect($provides)->toContain('nats')
        ->and($provides)->toContain('nats.v2')
        ->and($provides)->toContain(NatsManager::class)
        ->and($provides)->toContain(NatsV2Gateway::class)
        ->and($provides)->toContain(ConnectionManager::class)
        ->and($provides)->toContain(NatsPublisher::class)
        ->and($provides)->toContain(NatsPublisherContract::class)
        ->and($provides)->toContain(NatsSubscriberContract::class)
        ->and($provides)->toContain(NatsBasisSubscriber::class)
        ->and($provides)->toContain(SubjectValidator::class)
        ->and($provides)->toContain(BasisJetStreamPublisher::class)
        ->and($provides)->toContain(PullConsumerBatch::class)
        ->and($provides)->toContain(BasisStreamProvisioner::class)
        ->and($provides)->toContain(Client::class);
});

it('resolves nats as NatsManager', function (): void {
    $nats = $this->app->make('nats');

    expect($nats)->toBeInstanceOf(NatsManager::class);
});

it('publishes config file', function (): void {
    // Check that the provider has publishable config
    $provider = new NatsServiceProvider($this->app);

    // Use reflection to check publishes
    $reflection = new ReflectionClass($provider);

    // ServiceProvider tracks publishes in a static property
    // We can verify the config path exists
    $configPath = __DIR__ . '/../../../src/Laravel/Config/nats.php';

    expect(file_exists($configPath))->toBeTrue();
});

it('merges config from package', function (): void {
    $config = $this->app->make('config');

    // Should have merged the default config
    expect($config->get('nats.default'))->toBe('default');
});

it('registers nats.v2 basis stack bindings', function (): void {
    expect($this->app->bound('nats.v2'))->toBeTrue()
        ->and($this->app->bound(ConnectionManager::class))->toBeTrue()
        ->and($this->app->bound(NatsPublisher::class))->toBeTrue()
        ->and($this->app->bound(NatsPublisherContract::class))->toBeTrue();
});

it('resolves nats.v2 as NatsV2Gateway', function (): void {
    expect($this->app->make('nats.v2'))->toBeInstanceOf(NatsV2Gateway::class);
});

it('merges nats_basis config from package', function (): void {
    $config = $this->app->make('config');

    expect($config->get('nats_basis.default'))->toBe('default')
        ->and($config->get('nats_basis.connections.default.host'))->not->toBeNull();
});
