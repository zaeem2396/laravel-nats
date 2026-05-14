<?php

declare(strict_types=1);

use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Laravel\NatsV2Gateway;

it('resolves NatsV2 facade root to NatsV2Gateway', function (): void {
    expect(NatsV2::getFacadeRoot())->toBeInstanceOf(NatsV2Gateway::class);
});

it('selects a configured connection through the facade root', function (): void {
    config()->set('nats_basis.connection_selection.subject_prefixes', [
        'orders.' => 'orders',
    ]);

    expect(NatsV2::selectConnection('orders.created'))->toBe('orders')
        ->and(NatsV2::selectConnection('billing.created'))->toBeNull();
});

it('exposes the outbox dispatch helper on the facade root', function (): void {
    expect(method_exists(NatsV2::getFacadeRoot(), 'dispatchOutbox'))->toBeTrue();
});
