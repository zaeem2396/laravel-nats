# Example: named connection

```php
NatsV2::subscribe('x.y', $handler, null, 'secondary');
NatsV2::process('secondary', 1.0);
```
