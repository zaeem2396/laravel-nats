# Example: JetStream account info (NatsV2)

Requires NATS with JetStream enabled.

```bash
php artisan nats:v2:jetstream:info
```

Programmatically:

```php
use LaravelNats\Laravel\Facades\NatsV2;

$info = NatsV2::jetstream()->accountInfo();
```

See [JETSTREAM.md](../JETSTREAM.md) for publish, pull, and presets.

---

**v2.6:** JetStream publish helpers honor publish ACLs; queue internals are documented separately ([SECURITY.md](../SECURITY.md)).
