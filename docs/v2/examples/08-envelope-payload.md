# Example: read v2 envelope on consume

```php
NatsV2::subscribe('orders.created', function (InboundMessage $m): void {
    $env = $m->envelopePayload();
    $data = $env['data'] ?? null;
});
```
