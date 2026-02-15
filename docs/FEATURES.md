# Laravel NATS — Features

laravel-nats is a production-ready, Laravel-native integration for NATS and JetStream with full queue driver support.

## Table of Contents

1. [Publish Messages](#-1-publish-messages)
2. [Subscribe to Subjects](#-2-subscribe-to-subjects)
3. [Request / Reply Pattern](#-3-request--reply-pattern)
4. [Full Laravel Queue Driver](#-4-full-laravel-queue-driver)
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

## 🔁 3. Request / Reply Pattern

Built-in support for synchronous request-response communication.

**Description**

Perfect for microservice-style communication where a response is required. The request blocks until a reply is received or the timeout expires. Ideal for RPC-style calls between services.

**Example**

```php
use LaravelNats\Laravel\Facades\Nats;

$response = Nats::request('orders.get', ['order_id' => 1001], timeout: 5.0);
// Or: Nats::connection('secondary')->request(...)
$order = $response->getDecodedPayload();
// Connection-specific: Nats::connection('secondary')->request(...)
```

**Notes**

- Uses `_INBOX.*` for reply routing; no manual reply subject needed
- Configurable timeout (default 5.0 seconds)
- Returns `MessageInterface` with `getDecodedPayload()` for parsed response
- Throws `TimeoutException` if no reply within timeout

**Requirements**

- NATS Server 2.x
- PHP 8.2+

**See also**

- [README — Request/Reply](../README.md#requestreply)

---

## 🗂 4. Full Laravel Queue Driver

Use NATS as a first-class Laravel queue driver.

**Description**

Supports job retries, backoff strategies, delayed jobs (with JetStream), failed job handling, and Dead Letter Queues (DLQ).

**Example**

```php
dispatch(new ProcessOrder($order))->onConnection('nats');
```

Run the worker:

```bash
php artisan queue:work nats
```

**Worker options:** `--queue`, `--tries`, `--timeout`, `--memory`, `--sleep`, `--once`

**Job retries and backoff**

```php
class ProcessOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 5;
    public $backoff = [10, 30, 60]; // Linear backoff in seconds
}
```

**Failed jobs and DLQ**

Configure `dead_letter_queue` in queue config to route failed jobs to a separate NATS subject. Jobs also stored in Laravel's `failed_jobs` table.

**Requirements**

- NATS Server 2.x (JetStream for delayed jobs)
- PHP 8.2+
- Laravel 10.x / 11.x / 12.x

**See also**

- [README — Queue Driver](../README.md#queue-driver)
- [README — Failed Jobs & DLQ](../README.md#dead-letter-queue-dlq)

---

_Features 3–4 complete. Remaining features (5–10) documented in subsequent releases._

---

### Feature 3–4 Summary

- **Request/Reply:** `Nats::request($subject, $payload, timeout: 5.0)` — synchronous RPC-style messaging
- **Queue Driver:** `dispatch($job)->onConnection('nats')`, `php artisan queue:work nats` — full Laravel queue contract

**Microservice use case:** Use Request/Reply for sync RPC; use Queue Driver for async job processing.

