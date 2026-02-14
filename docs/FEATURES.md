# Laravel NATS — Features

laravel-nats is a production-ready, Laravel-native integration for NATS and JetStream with full queue driver support.

---

## 📡 1. Publish Messages

Publish messages to any NATS subject with automatic JSON encoding.

**Description**

Send structured payloads easily using a Laravel-friendly facade. Messages are automatically serialized to JSON for interoperability.

**Example**

```php
use LaravelNats\Laravel\Facades\Nats;

Nats::publish('orders.created', [
    'order_id' => 1001,
    'amount' => 2500,
]);

// Publish to a specific connection
Nats::connection('analytics')->publish('events.tracked', $data);

// With optional headers (NATS 2.2+)
Nats::publish('orders.created', $payload, ['X-Trace-Id' => 'abc-123']);
```

**Notes**

- Arrays and objects are auto-serialized to JSON
- Raw strings are published as-is
- Headers require NATS 2.2+ server
