# Example: unsubscribe by id

```php
$id = NatsV2::subscribe('tmp', $handler);
// ...
NatsV2::unsubscribe($id);
```
