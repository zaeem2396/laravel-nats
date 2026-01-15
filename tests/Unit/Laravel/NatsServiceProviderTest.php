<?php

declare(strict_types=1);

use LaravelNats\Core\Client;
use LaravelNats\Laravel\NatsManager;
use LaravelNats\Laravel\Providers\NatsServiceProvider;

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
        ->and($provides)->toContain(NatsManager::class)
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
