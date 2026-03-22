<?php

declare(strict_types=1);

use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

beforeEach(function (): void {
    if (! $this->isNatsAvailable()) {
        $this->markTestSkipped('NATS server not available at localhost:4222');
    }
});

it('subscribes and receives v2 envelope payload via NatsV2', function (): void {
    $subject = 'v2.sub.test.' . uniqid();
    $received = null;

    NatsV2::subscribe($subject, function (InboundMessage $message) use (&$received): void {
        $received = $message->envelopePayload();
    });

    NatsV2::publish($subject, ['n' => 42]);

    $deadline = microtime(true) + 3.0;
    while ($received === null && microtime(true) < $deadline) {
        NatsV2::process(null, 0.2);
    }

    expect($received)->not->toBeNull()
        ->and($received['data'])->toBe(['n' => 42]);

    NatsV2::disconnectAll();
});
