# Example: Artisan listener

```bash
php artisan nats:v2:listen events.debug --timeout=2
```

---

**v2.6:** `nats:v2:listen` uses the same subscriber ACL rules as `NatsV2::subscribe` ([SECURITY.md](../SECURITY.md)).
