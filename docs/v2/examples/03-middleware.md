# Example: register LogInboundMiddleware

In `config/nats_basis.php`:

```php
'middleware' => [
    \LaravelNats\Subscriber\Middleware\LogInboundMiddleware::class,
],
```
