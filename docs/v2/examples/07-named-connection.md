# Example: named connection

```php
NatsV2::subscribe('x.y', $handler, null, 'secondary');
NatsV2::process('secondary', 1.0);
```

---

**v2.6:** ACL and TLS checks use the resolved `nats_basis` connection entry ([SECURITY.md](../SECURITY.md)).
