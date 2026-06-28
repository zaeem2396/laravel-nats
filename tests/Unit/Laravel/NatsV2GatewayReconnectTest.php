<?php

declare(strict_types=1);

use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Tests\TestCase;

it('reconnects via NatsV2 gateway when NATS is available', function (): void {
    TestCase::skipUnlessNatsReachable();

    $gateway = NatsV2::getFacadeRoot();

    expect($gateway->ping())->toBeTrue();

    $client = $gateway->reconnect();

    expect($client->ping())->toBeTrue();

    $gateway->disconnectAll();
});

it('reconnects legacy Nats manager connection when NATS is available', function (): void {
    TestCase::skipUnlessNatsReachable();

    $manager = $this->app->make('nats');
    $client = $manager->connection();

    expect($client->isConnected())->toBeTrue();

    $reconnected = $manager->reconnect();

    expect($reconnected->isConnected())->toBeTrue();

    $manager->disconnectAll();
});
