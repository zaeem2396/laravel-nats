# Example: config validation and subject ACL

**Package 1.5.0+.** Optional Laravel-side guards — they do not replace NATS server authz.

### Environment

```env
NATS_BASIS_VALIDATE_CONFIG=true
NATS_TLS_REQUIRE_IN_PRODUCTION=true
NATS_ACL_ENABLED=true
NATS_ACL_PUBLISH_PREFIXES=orders.,events.
NATS_ACL_SUBSCRIBE_PREFIXES=orders.,events.
```

### Validate in CI or deploy

```bash
php artisan nats:v2:config:validate
```

### Publish / subscribe under allowlists

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

// Allowed when prefixes include "orders."
NatsV2::publish('orders.created', ['id' => 42]);

NatsV2::subscribe('orders.created', function (InboundMessage $m): void {
    // ...
});

while (true) {
    NatsV2::process(null, 1.0);
}
```

Subjects outside the allowlists raise ACL exceptions before traffic hits NATS.

---

Full threat model and TLS notes: [SECURITY.md](../SECURITY.md).
