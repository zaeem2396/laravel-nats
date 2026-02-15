# Laravel NATS — Features

laravel-nats is a production-ready, Laravel-native integration for NATS and JetStream with full queue driver support.

## Table of Contents

1. [Publish Messages](#-1-publish-messages)
2. [Subscribe to Subjects](#-2-subscribe-to-subjects)
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

The `Nats` facade is registered as an alias by the package. Use the full namespace or the `Nats` alias:

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
- NATS default max payload is 1MB; for larger messages, store data externally and pass a reference

**Requirements**

- NATS Server 2.x
- PHP 8.2+

**See also**

- [README — Publishing Messages](../README.md#publishing-messages)
- [README — Troubleshooting (Message Size)](../README.md#message-size-limits)

---

## 📥 2. Subscribe to Subjects

Subscribe to subjects and process messages using callbacks.

**Description**

Supports standard and wildcard subjects (`*`, `>`). Messages are decoded automatically when using the default JSON serializer.

**Example**

```php
use LaravelNats\Laravel\Facades\Nats;

Nats::subscribe('orders.*', function ($message) {
    logger()->info('Order event received', $message->getDecodedPayload());
});

// Process incoming messages (wait up to 1 second)
Nats::process(1.0);
```

---

_Remaining features (3–10) documented in subsequent releases._
