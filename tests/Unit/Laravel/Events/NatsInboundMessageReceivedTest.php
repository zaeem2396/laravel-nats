<?php

declare(strict_types=1);

use LaravelNats\Laravel\Events\NatsInboundMessageReceived;
use LaravelNats\Subscriber\InboundMessage;

it('exposes the inbound message', function (): void {
    $message = new InboundMessage('orders.created', '{"id":1}', ['X-Test' => '1'], null);
    $event = new NatsInboundMessageReceived($message);

    expect($event->message)->toBe($message)
        ->and($event->message->subject)->toBe('orders.created');
});
