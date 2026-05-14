<?php

declare(strict_types=1);

use LaravelNats\Outbox\NatsOutboxMessage;
use LaravelNats\Support\NatsHeaderBag;

it('stores publish details for an outbox row', function (): void {
    $message = new NatsOutboxMessage(
        id: 'row-1',
        subject: 'orders.created',
        payload: ['order_id' => 123],
        headers: NatsHeaderBag::make()->withRequestId('req-1'),
        connection: 'orders',
    );

    expect($message->id)->toBe('row-1')
        ->and($message->subject)->toBe('orders.created')
        ->and($message->payload)->toBe(['order_id' => 123])
        ->and($message->headers)->toBe(['X-Request-Id' => 'req-1'])
        ->and($message->connection)->toBe('orders');
});
