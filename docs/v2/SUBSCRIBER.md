# NatsV2 subscriber (basis-company/nats wrapper)

The subscriber API (package **1.3.0+**) is a **Laravel wrapper** around `Basis\Nats\Client::subscribe` / `subscribeQueue`: you register PHP callables that receive an `LaravelNats\Subscriber\InboundMessage` value object; the dependency still owns the NATS socket and protocol.

## Configuration

See `nats_basis.subscriber` in `config/nats_basis.php` (merged from the package; publish with `php artisan vendor:publish --tag=nats-config`). For **persisted / replayable** workloads, consider **JetStream** via [JETSTREAM.md](JETSTREAM.md).

| Key | Purpose |
|-----|---------|
| `subject_max_length` | Reject subscription subjects longer than this (default 512). |
| `warn_on_unconventional_subject` | When `true`, may warn on unusual subject patterns (off by default). |
| `decode_envelope` | Reserved; use `InboundMessage::envelopePayload()` instead. |
| `dispatch_events` | When `true`, dispatches `LaravelNats\Laravel\Events\NatsInboundMessageReceived` before your handler. |
| `middleware` | List of `InboundMiddleware` class names (resolved from the container). |

Environment: `NATS_SUBJECT_MAX_LENGTH`, `NATS_SUBSCRIBER_DISPATCH_EVENTS`, `NATS_SUBSCRIBER_DECODE_ENVELOPE` (reserved for future use).

## Subscribe

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

$id = NatsV2::subscribe('orders.created', function (InboundMessage $message): void {
    $data = $message->envelopePayload(); // v2 publisher envelope, or null
    // ...
});
```

## Queue groups

Pass a queue group for load-balanced consumers (same as NATS queue groups):

```php
NatsV2::subscribe('jobs.run', $handler, 'worker-pool');
```

## Process loop

The basis client is pull-based: after you subscribe, call `process()` in a loop (or use the Artisan command below).

```php
while ($running) {
    NatsV2::process(null, 1.0); // default connection, 1s blocking read
}
```

## Unsubscribe

```php
NatsV2::unsubscribe($id);
NatsV2::unsubscribeAll(); // or scoped: unsubscribeAll('connection-name')
```

## Middleware

Implement `LaravelNats\Subscriber\Middleware\InboundMiddleware` and add the class name to `nats_basis.subscriber.middleware`. The package ships with `LogInboundMiddleware` (commented out by default) for debug logging via `Psr\Log\LoggerInterface`, and **`IdempotencyInboundMiddleware`** for deduplication when **`nats_basis.idempotency.enabled`** is true ([IDEMPOTENCY.md](IDEMPOTENCY.md)).

## Events

Set `nats_basis.subscriber.dispatch_events` to `true` to fire `NatsInboundMessageReceived` before your callback (useful for global logging or metrics).

## Artisan: `nats:v2:listen`

```bash
php artisan nats:v2:listen orders.debug --connection=default --timeout=1
```

Prints message bodies to the console. Uses SIGINT/SIGTERM (when `pcntl` is available) for graceful stop, then unsubscribes the listener id.

## Limits

- **One active subscription** per tuple `(connection name, subject, queue group)` at a time; a duplicate throws `SubscriptionConflictException`.
- Unsubscribe uses the basis client's subject-based unsubscribe (same subject removes that subscription).

## Inbound message

`InboundMessage` exposes `subject`, `body`, `headers`, `replyTo`, plus `decodedJson()` and `envelopePayload()` for v2 publisher JSON.

## Testing

Use a real NATS server (Docker in CI). Subscribe, publish with `NatsV2::publish`, then `process()` until the handler runs. See `tests/Feature/NatsV2SubscriberTest.php`.

## See also

- [GUIDE](GUIDE.md) - publish and config  
- [MIGRATION](MIGRATION.md) - dual stack with legacy `Nats::subscribe`  
- [FAQ](FAQ.md)
