<?php

declare(strict_types=1);

use LaravelNats\Support\NatsHeaderBag;

it('builds common header names fluently', function (): void {
    $headers = NatsHeaderBag::make()
        ->withRequestId('req-1')
        ->withCorrelationId('corr-1')
        ->withIdempotencyKey('idem-1')
        ->toArray();

    expect($headers)->toBe([
        'X-Request-Id' => 'req-1',
        'Nats-Correlation-Id' => 'corr-1',
        'Nats-Idempotency-Key' => 'idem-1',
    ]);
});

it('supports repeated header values', function (): void {
    $named = NatsHeaderBag::make()
        ->withMany('X-Tag', ['one', 'two'])
        ->toNamedValues();

    expect($named)->toBe(['X-Tag' => ['one', 'two']]);
});

it('validates traceparent values', function (): void {
    expect(
        NatsHeaderBag::make()
            ->withTraceParent('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01')
            ->toArray(),
    )->toHaveKey('traceparent');

    expect(fn () => NatsHeaderBag::make()->withTraceParent('bad'))->toThrow(InvalidArgumentException::class);
});
