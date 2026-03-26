<?php

declare(strict_types=1);

use LaravelNats\JetStream\BasisJetStreamManager;
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Laravel\NatsV2Gateway;

it('resolves NatsV2Gateway with JetStream entry points', function (): void {
    $gw = $this->app->make(NatsV2Gateway::class);
    expect($gw->jetstream())->toBeInstanceOf(BasisJetStreamManager::class);
});

it('NatsV2 facade proxies jetstream()', function (): void {
    expect(NatsV2::jetstream())->toBeInstanceOf(BasisJetStreamManager::class);
});

it('rejects empty jetStreamProvisionPreset key', function (): void {
    $gw = $this->app->make(NatsV2Gateway::class);
    expect(fn () => $gw->jetStreamProvisionPreset(''))->toThrow(InvalidArgumentException::class);
});
