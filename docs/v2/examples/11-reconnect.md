# Example: reconnect after a dropped session

From **1.6.2+**, recreate a cached basis client when the session is no longer usable. Prefer this when you detect a broken connection and want a clean client without restarting the process.

```php
use LaravelNats\Laravel\Facades\NatsV2;

try {
    NatsV2::publish('health.ping', ['ok' => true]);
} catch (Throwable $e) {
    // Drop the cached client and open a fresh one on the default connection.
    NatsV2::reconnect();
    NatsV2::publish('health.ping', ['ok' => true]);
}
```

Named connection:

```php
NatsV2::reconnect('orders');
NatsV2::publish('orders.created', ['id' => 1], connection: 'orders');
```

Legacy wire stack (still supported):

```php
use LaravelNats\Laravel\Facades\Nats;

Nats::reconnect();
```

---

Automatic reconnect inside **basis-company/nats** remains controlled by `nats_basis.connections.*.reconnect`. Manual `NatsV2::reconnect()` clears this package’s client cache. See [GUIDE.md](../GUIDE.md) and [CLIENT_FEATURES.md](../CLIENT_FEATURES.md).
