# Example: register LogInboundMiddleware

In `config/nats_basis.php`:

```php
'middleware' => [
    \LaravelNats\Subscriber\Middleware\LogInboundMiddleware::class,
],
```

---

**v2.6:** Middleware runs after ACL validation on the subscribe subject ([SECURITY.md](../SECURITY.md)).
