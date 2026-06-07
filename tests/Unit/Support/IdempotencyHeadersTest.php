<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Support\IdempotencyHeaders;

it('returns headers unchanged when idempotency key is empty', function (): void {
    $config = new Repository(['nats_basis.idempotency.header_name' => 'Nats-Idempotency-Key']);
    $headers = ['X-Custom' => '1'];

    expect(IdempotencyHeaders::mergeForPublish($config, $headers, null))->toBe($headers)
        ->and(IdempotencyHeaders::mergeForPublish($config, $headers, ''))->toBe($headers);
});

it('merges idempotency header when key is provided', function (): void {
    $config = new Repository(['nats_basis.idempotency.header_name' => 'Nats-Idempotency-Key']);

    $merged = IdempotencyHeaders::mergeForPublish($config, [], 'pay-123');

    expect($merged)->toHaveKey('Nats-Idempotency-Key', 'pay-123');
});

it('uses configured header name and does not override existing values', function (): void {
    $config = new Repository(['nats_basis.idempotency.header_name' => 'X-Idempotency']);
    $headers = ['X-Idempotency' => 'existing'];

    expect(IdempotencyHeaders::mergeForPublish($config, $headers, 'new-key'))
        ->toBe($headers);
});

it('falls back to default header name when config name is empty', function (): void {
    $config = new Repository(['nats_basis.idempotency.header_name' => '']);

    expect(IdempotencyHeaders::mergeForPublish($config, [], 'k'))
        ->toHaveKey(IdempotencyHeaders::DEFAULT_HEADER, 'k');
});
