# Example: queue group

```php
NatsV2::subscribe('tasks.run', function (InboundMessage $m): void {
    // ...
}, 'workers');
```
