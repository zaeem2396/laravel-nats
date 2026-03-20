<?php

declare(strict_types=1);

use LaravelNats\Support\MessageEnvelope;

it('builds envelope with id, type, version, and data', function (): void {
    $envelope = MessageEnvelope::create('orders.created', ['order_id' => 1], 'v1');

    $array = $envelope->toArray();

    expect($array)->toHaveKeys(['id', 'type', 'version', 'data'])
        ->and($array['type'])->toBe('orders.created')
        ->and($array['version'])->toBe('v1')
        ->and($array['data'])->toBe(['order_id' => 1])
        ->and($array['id'])->toMatch('/^[0-9a-f-]{36}$/');
});

it('generates unique ids', function (): void {
    $a = MessageEnvelope::create('a', [], 'v1')->getId();
    $b = MessageEnvelope::create('b', [], 'v1')->getId();

    expect($a)->not->toBe($b);
});
