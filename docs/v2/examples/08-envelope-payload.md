# Example: read v2 envelope on consume

```php
NatsV2::subscribe('orders.created', function (InboundMessage $m): void {
    $env = $m->envelopePayload();
    $data = $env['data'] ?? null;
});
```

---

**v2.6:** Envelope publishers still require allowed publish prefixes when ACL is on ([SECURITY.md](../SECURITY.md)).
