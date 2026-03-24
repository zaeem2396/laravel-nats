<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use LaravelNats\Subscriber\InboundMessage;

/**
 * Dispatched before your subscription callback when `nats_basis.subscriber.dispatch_events` is true.
 */
final class NatsInboundMessageReceived
{
    use Dispatchable;

    public function __construct(
        public readonly InboundMessage $message,
    ) {
    }
}
