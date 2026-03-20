<?php

declare(strict_types=1);

use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Laravel\NatsV2Gateway;

it('resolves NatsV2 facade root to NatsV2Gateway', function (): void {
    expect(NatsV2::getFacadeRoot())->toBeInstanceOf(NatsV2Gateway::class);
});
