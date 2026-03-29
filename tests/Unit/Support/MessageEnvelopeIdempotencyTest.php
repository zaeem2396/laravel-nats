<?php

declare(strict_types=1);

use LaravelNats\Support\MessageEnvelope;

describe('MessageEnvelope idempotency', function (): void {
    it('omits idempotency_key from array when not set', function (): void {
        $e = MessageEnvelope::create('orders.x', ['a' => 1], 'v1');
        $arr = $e->toArray();

        expect($arr)->not->toHaveKey('idempotency_key');
    });

    it('includes idempotency_key when provided', function (): void {
        $e = MessageEnvelope::create('orders.x', ['a' => 1], 'v1', 'idem-abc');
        $arr = $e->toArray();

        expect($arr['idempotency_key'])->toBe('idem-abc')
            ->and($e->getIdempotencyKey())->toBe('idem-abc');
    });

    it('drops empty idempotency key at create', function (): void {
        $e = MessageEnvelope::create('orders.x', ['a' => 1], 'v1', '');
        expect($e->toArray())->not->toHaveKey('idempotency_key');
    });
});
