# Laravel NATS — Features

laravel-nats is a production-ready, Laravel-native integration for NATS and JetStream with full queue driver support.

## Table of Contents

1. [Publish Messages](#-1-publish-messages)
2. [Subscribe to Subjects](#-2-subscribe-to-subjects)
3. [Request / Reply Pattern](#-3-request--reply-pattern) _(coming soon)_
4. [Full Laravel Queue Driver](#-4-full-laravel-queue-driver) _(coming soon)_
5. [JetStream Support](#-5-jetstream-support) _(coming soon)_
6. [Delayed Jobs via JetStream](#-6-delayed-jobs-via-jetstream) _(coming soon)_
7. [Multiple Connections Support](#-7-multiple-connections-support)
8. [Wildcard Subscriptions](#-8-wildcard-subscriptions)
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

Supports standard and wildcard subjects (`*`, `>`). Messages are decoded automatically when using the default JSON serializer. Ideal for event-driven and pub/sub architectures.

**Example**

The callback receives a `MessageInterface`; use `getDecodedPayload()` for JSON-decoded data:

```php
use LaravelNats\Laravel\Facades\Nats;

Nats::subscribe('orders.*', function ($message) {
    logger()->info('Order event received', $message->getDecodedPayload());
});

// Process incoming messages (wait up to 1 second)
Nats::process(1.0);

// For long-running subscribers, use a loop:
// while (true) { Nats::process(1.0); }
```

**Queue groups**

Use a queue group to distribute messages across multiple subscribers (load balancing):

```php
Nats::subscribe('orders.process', function ($message) {
    // Only one subscriber receives each message
}, 'order-workers');
```

**Notes**

- Callback receives a `MessageInterface` instance; use `getDecodedPayload()` for parsed data or `getPayload()` for raw string
- `process($timeout)` blocks and dispatches messages to callbacks; use a loop for continuous processing
- Wildcards: `*` matches one token, `>` matches one or more tokens

**Unsubscribe**

Call `Nats::process()` in a loop for long-running subscribers. Use `unsubscribe()` to stop receiving:

```php
$sid = Nats::subscribe('orders.*', $callback);
Nats::process(1.0);
Nats::unsubscribe($sid);
```

**Requirements**

- NATS Server 2.x
- PHP 8.2+

**See also**

- [README — Subscribing to Messages](../README.md#subscribing-to-messages)
- [README — Queue Groups](../README.md#queue-groups-load-balancing)

Subscribe on a specific connection: `Nats::connection('analytics')->subscribe(...)`.

---

## 🔄 7. Multiple Connections Support

Define and use multiple NATS connections in your config.

**Description**

Configure named connections in `config/nats.php` and switch between them for different workloads (e.g. analytics, orders, notifications).

**Example**

```php
use LaravelNats\Laravel\Facades\Nats;

// Publish to analytics connection
Nats::connection('analytics')->publish('events.page_viewed', $data);

// Subscribe on a specific connection
Nats::connection('secondary')->subscribe('orders.*', $callback);
```

**Default connection:** Use `NATS_CONNECTION` env var or omit `connection()` for default.

**Configuration**

```php
// config/nats.php
'connections' => [
    'default' => ['host' => 'localhost', 'port' => 4222],
    'analytics' => ['host' => 'nats-analytics.example.com', 'port' => 4222],
],
```

**Requirements**

- NATS Server 2.x
- PHP 8.2+

**See also**

- [README — Multiple Connections](../README.md#multiple-connections)

---

## 🧵 8. Wildcard Subscriptions

Subscribe using NATS wildcard patterns for flexible subject matching.

**Description**

- `*` matches exactly one token: `orders.*` matches `orders.created`, `orders.updated`
- `>` matches one or more tokens: `payments.>` matches `payments.created`, `payments.failed`, `payments.refund.initiated`

**Example**

```php
use LaravelNats\Laravel\Facades\Nats;

// Single-token wildcard
Nats::subscribe('orders.*', function ($message) {
    // Handles orders.created, orders.updated, orders.deleted
});

// Multi-token wildcard
Nats::subscribe('payments.>', function ($message) {
    // Handles payments.created, payments.failed, payments.refund.initiated
});

Nats::process(1.0);
```

**Notes**

- Wildcards only apply to subscriptions, not publish subjects
- Use unique subjects per test to avoid cross-test message leakage

**Requirements**

- NATS Server 2.x
- PHP 8.2+

**See also**

- [README — Wildcards](../README.md#wildcards)

---

_Features 7–8 complete. Remaining features (3–6, 9–10) documented in subsequent releases._

