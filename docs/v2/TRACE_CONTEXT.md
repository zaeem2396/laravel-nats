# W3C Trace Context

`NatsV2` can propagate lightweight W3C trace context from an active Laravel HTTP request into HPUB headers.

## Configure

```env
NATS_TRACE_CONTEXT_INJECT=true
NATS_TRACEPARENT_HEADER=traceparent
NATS_TRACESTATE_HEADER=tracestate
```

When enabled, `NatsPublisher` copies a valid `traceparent` and optional `tracestate` from the current request unless the publish call already supplied those headers. Explicit publish headers win case-insensitively.

## Publish

```php
use LaravelNats\Support\NatsHeaderBag;

$headers = NatsHeaderBag::make()
    ->withTraceContext(
        '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        'rojo=00f067aa0ba902b7',
    )
    ->toArray();

NatsV2::publish('orders.created', ['order_id' => 123], $headers);
```

## Subscribe

```php
NatsV2::subscribe('orders.created', function ($message): void {
    $traceparent = $message->traceParent();
    $tracestate = $message->traceState();
});
```

## Validation

`LaravelNats\Support\TraceContextHeaders::isValidTraceParent()` validates the basic W3C `version-trace-id-parent-id-flags` shape and rejects all-zero trace or parent ids. It does not provide a full tracing SDK; bridge these headers to OpenTelemetry or your tracing library in application middleware.

## See also

- [CORRELATION.md](CORRELATION.md)
- [OBSERVABILITY.md](OBSERVABILITY.md)
- [CLIENT_FEATURES.md](CLIENT_FEATURES.md)
