# Example: queue group

```php
NatsV2::subscribe('tasks.run', function (InboundMessage $m): void {
    // ...
}, 'workers');
```

---

**v2.6:** Queue group names do not bypass ACL checks on the subject argument ([SECURITY.md](../SECURITY.md)).
