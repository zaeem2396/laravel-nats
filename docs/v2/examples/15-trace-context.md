# Example: W3C trace context on publish / subscribe

**Package 1.6.0+.** Propagate `traceparent` / `tracestate` from an HTTP request or set them explicitly.

### Auto-inject from the current request

```env
NATS_TRACE_CONTEXT_INJECT=true
```

```php
use LaravelNats\Laravel\Facades\NatsV2;

// Inside an HTTP request that already carries valid W3C headers:
NatsV2::publish('orders.created', ['order_id' => 123]);
```

### Explicit headers

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Support\NatsHeaderBag;

$headers = NatsHeaderBag::make()
    ->withTraceContext(
        '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        'rojo=00f067aa0ba902b7',
    )
    ->toArray();

NatsV2::publish('orders.created', ['order_id' => 123], $headers);
```

### Read on subscribe

```php
use LaravelNats\Subscriber\InboundMessage;

NatsV2::subscribe('orders.created', function (InboundMessage $message): void {
    $traceparent = $message->traceParent();
    $tracestate = $message->traceState();
});
```

Explicit publish headers win over auto-injection (case-insensitive). See [TRACE_CONTEXT.md](../TRACE_CONTEXT.md).
