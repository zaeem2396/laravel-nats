# Example: named connection

```php
NatsV2::subscribe('x.y', $handler, null, 'secondary');
NatsV2::process('secondary', 1.0);
```

Subject-prefix selection can remove repeated connection arguments:

```php
// config/nats_basis.php
'connection_selection' => [
    'subject_prefixes' => [
        'orders.' => 'orders',
    ],
],

NatsV2::publish('orders.created', ['order_id' => 123]); // uses "orders"
```

---

**v2.6 (1.5.0+):** ACL and TLS checks use the resolved `nats_basis` connection entry ([SECURITY.md](../SECURITY.md)).
**v2.7 (1.6.0+):** Subject-prefix selection is documented in [CONNECTION_SELECTION.md](../CONNECTION_SELECTION.md).
