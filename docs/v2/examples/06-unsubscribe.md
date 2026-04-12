# Example: unsubscribe by id

```php
$id = NatsV2::subscribe('tmp', $handler);
// ...
NatsV2::unsubscribe($id);
```

---

**v2.6:** ACL applies when subscribing; unsubscribing does not change prefix rules ([SECURITY.md](../SECURITY.md)).
