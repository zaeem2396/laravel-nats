# Laravel NATS — Features

laravel-nats is a production-ready, Laravel-native integration for NATS and JetStream with full queue driver support.

## Table of Contents

1. [Publish Messages](#-1-publish-messages)
2. [Subscribe to Subjects](#-2-subscribe-to-subjects)
3. [Request / Reply Pattern](#-3-request--reply-pattern)
4. [Full Laravel Queue Driver](#-4-full-laravel-queue-driver)
5. [JetStream Support](#-5-jetstream-support)
6. [Delayed Jobs via JetStream](#-6-delayed-jobs-via-jetstream)
7. [Multiple Connections Support](#-7-multiple-connections-support)
8. [Wildcard Subscriptions](#-8-wildcard-subscriptions)
9. [Artisan Commands](#-9-artisan-commands)
10. [Laravel-Native API Design](#-10-laravel-native-api-design)

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

Supports job retries, backoff strategies, delayed jobs (with JetStream), failed job handling, and Dead Letter Queues (DLQ). Fully compatible with Laravel's `queue:work` command.

**Example**

```php
dispatch(new ProcessOrder($order))->onConnection('nats');
```

Run the worker (either command):

```bash
php artisan queue:work nats
# Or use the dedicated NATS worker (Phase 4.1) with PID file for Supervisor/systemd:
php artisan nats:work --pidfile=/var/run/nats-worker.pid
```

**Worker options:** `--queue`, `--tries`, `--timeout`, `--memory`, `--sleep`, `--once`. For `nats:work`: also `--connection`, `--name`, `--pidfile`, `--stop-when-empty`.

**Subject-based consumer (Phase 4.2 — Subject-Based Consumer):** Use `nats:consume {subject}` to subscribe to subject(s) with optional queue group and handler class. Handlers implement `LaravelNats\Contracts\Messaging\MessageHandlerInterface` and receive each message via `handle(MessageInterface $message)`. Options: `--connection=`, `--queue=`, `--handler=`, `--subjects=` (comma-separated). Supports wildcards `*` and `>`.

Example handler:

```php
use LaravelNats\Contracts\Messaging\MessageHandlerInterface;
use LaravelNats\Contracts\Messaging\MessageInterface;

class MyHandler implements MessageHandlerInterface
{
    public function handle(MessageInterface $message): void
    {
        // Process $message->getSubject(), $message->getPayload(), etc.
    }
}
```

Run: `php artisan nats:consume "events.>" --handler=MyHandler`. See README "Subject-based consumer (Phase 4.2)" for full options and queue groups.

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
- [README — Delayed Jobs](../README.md#delayed-jobs-jetstream)

---

## 🚀 5. JetStream Support

Advanced NATS JetStream integration for persistence and streaming.

**Description**

Stream creation and management, consumer configuration, durable consumers, message acknowledgements. JetStream adds persistence, replay, and exactly-once semantics.

**Example**

```php
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Laravel\Facades\Nats;

$js = Nats::jetstream();

if ($js->isAvailable()) {
    $config = new StreamConfig('ORDERS', ['orders.*'])
        ->withMaxMessages(10000)
        ->withStorage(\LaravelNats\Core\JetStream\StreamConfig::STORAGE_FILE);

    $info = $js->createStream($config);
}
```

**Consumer and ack:** Use `ConsumerConfig` for pull consumers; `fetchNextMessage()` + `ack()` / `nak()` / `term()` for message handling.

**Key operations:** `createStream`, `getStreamInfo`, `updateStream`, `deleteStream`, `purgeStream`, `listStreams`, `createConsumer`, `listConsumers`, `fetchNextMessage`, `ack`, `nak`, `term`, `getAccountInfo`

**Requirements**

- NATS Server 2.9+ with `--jetstream`
- PHP 8.2+

**Domain support:** `Nats::jetstream(null, new JetStreamConfig('my-domain'))` for multi-tenancy.

**See also**

- [README — JetStream Support](../README.md#jetstream-support)
- [README — Stream Management](../README.md#stream-management)
- [README — Consumer Management](../README.md#consumer-management)

---

## ⏳ 6. Delayed Jobs via JetStream

Schedule jobs using JetStream's delayed delivery mechanism.

**Description**

When `queue.delayed.enabled` is true, jobs dispatched with `later()` or `delay()` are stored in a JetStream stream and delivered to the queue when due.

**Example**

```php
dispatch(new SendReminderEmail($user))
    ->delay(now()->addMinutes(10))
    ->onConnection('nats');

// Or with seconds
Queue::connection('nats')->later(60, new ProcessOrder($order));
```

**Configuration**

Enable in queue config: `delayed => ['enabled' => true, 'stream' => '...', 'subject_prefix' => '...', 'consumer' => '...']`. Use `NATS_QUEUE_DELAYED_*` env vars.

**Requirements**

- NATS Server with JetStream enabled
- `queue.delayed.enabled` in nats queue connection

**See also**

- [README — Delayed Jobs (JetStream)](../README.md#delayed-jobs-jetstream)

**DelayStreamBootstrap:** Automatically ensures the delay stream and durable consumer exist when delayed is enabled.

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
- `orders.*` matches one token (e.g. `orders.created`); `orders.created.xyz` does not match

**Requirements**

- NATS Server 2.x
- PHP 8.2+

**See also**

- [README — Wildcards](../README.md#wildcards)

---

## 🛠 9. Artisan Commands

Manage streams and consumers directly from Laravel.

**Description**

CLI commands for JetStream stream and consumer management. All commands support `--connection=` for non-default NATS connections.

**Example**

```bash
# Streams
php artisan nats:stream:list [--connection=]
php artisan nats:stream:info ORDERS [--connection=]
php artisan nats:stream:create ORDERS "orders.*" [--description=] [--storage=file|memory]
php artisan nats:stream:delete ORDERS [--force]

# Consumers
php artisan nats:consumer:list ORDERS [--connection=]
php artisan nats:consumer:create ORDERS orders-consumer [--filter-subject=] [--ack-policy=explicit]
php artisan nats:consumer:delete ORDERS orders-consumer [--force]

# JetStream account
php artisan nats:jetstream:status [--connection=] [--json]
```

**Requirements**

- NATS Server with JetStream enabled
- PHP 8.2+

**See also**

- [README — Artisan Commands (JetStream)](../README.md#artisan-commands-jetstream)

---

## 🧩 10. Laravel-Native API Design

Designed to feel like core Laravel features.

**Description**

- **Familiar dispatch() integration** — Use `dispatch($job)->onConnection('nats')` as with Redis or SQS
- **Facade-based API** — `Nats::publish()`, `Nats::subscribe()`, `Nats::request()`, `Nats::jetstream()`
- **Config-driven setup** — `config/nats.php`, `NATS_*` env vars, `queue.connections.nats`
- **Seamless queue worker support** — `php artisan queue:work nats` with `--tries`, `--timeout`, `--queue`

**Example**

```php
// Same patterns as Laravel's Redis/Cache/Queue
Nats::publish('event', $payload);
dispatch($job)->onConnection('nats');
$js = Nats::jetstream();
```

---

_All 10 features documented. See README for full reference._

