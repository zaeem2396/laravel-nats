# Laravel NATS — Features

laravel-nats is a production-ready, Laravel-native integration for NATS and JetStream with full queue driver support.

## Table of Contents

1. [Publish Messages](#-1-publish-messages)
2. [Subscribe to Subjects](#-2-subscribe-to-subjects) _(coming soon)_
3. [Request / Reply Pattern](#-3-request--reply-pattern) _(coming soon)_
4. [Full Laravel Queue Driver](#-4-full-laravel-queue-driver) _(coming soon)_
5. [JetStream Support](#-5-jetstream-support) _(coming soon)_
6. [Delayed Jobs via JetStream](#-6-delayed-jobs-via-jetstream) _(coming soon)_
7. [Multiple Connections Support](#-7-multiple-connections-support) _(coming soon)_
8. [Wildcard Subscriptions](#-8-wildcard-subscriptions) _(coming soon)_
9. [Artisan Commands](#-9-artisan-commands) _(coming soon)_
10. [Laravel-Native API Design](#-10-laravel-native-api-design) _(coming soon)_

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
