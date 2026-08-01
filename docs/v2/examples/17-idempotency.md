# Example: idempotent publish and subscribe

**Package 1.4.0+.** Deduplicate handler work with a shared cache and inbound middleware.

### Publish with a key

```php
use LaravelNats\Laravel\Facades\NatsV2;

NatsV2::publish('orders.created', [
    'order_id' => 123,
    'idempotency_key' => 'order-123-created',
]);
```

The key is stripped from envelope `data` and mirrored as HPUB **`Nats-Idempotency-Key`** by default.

### Enable middleware

```env
NATS_IDEMPOTENCY_ENABLED=true
```

Register `IdempotencyInboundMiddleware` on your subscriber pipeline (see [IDEMPOTENCY.md](../IDEMPOTENCY.md)). Duplicate deliveries with the same key are skipped after the first successful handling when the cache store is shared across workers.

### Subscribe

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

NatsV2::subscribe('orders.created', function (InboundMessage $m): void {
    // Runs once per distinct idempotency key (per store TTL)
    ProcessOrder::dispatchSync($m->payload());
});

while (true) {
    NatsV2::process(null, 1.0);
}
```

---

Full setup: [IDEMPOTENCY.md](../IDEMPOTENCY.md).
