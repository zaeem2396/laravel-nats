# Example: basic subscribe

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

NatsV2::subscribe('hello.world', function (InboundMessage $m): void {
    logger()->info($m->body);
});

while (true) {
    NatsV2::process(null, 1.0);
}
```

---

**v2.6:** With `NATS_ACL_ENABLED`, subscription subjects must match `allowed_subscribe_prefixes` before handlers run ([SECURITY.md](../SECURITY.md)).
