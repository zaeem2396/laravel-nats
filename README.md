# Laravel NATS

[![Tests](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml)

A native NATS integration for Laravel that feels like home. Publish, subscribe, and request/reply with a familiar, expressive API.

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- NATS Server 2.x

## Installation

```bash
composer require zaeem2396/laravel-nats
```

The service provider will be auto-discovered. To publish the configuration file:

```bash
php artisan vendor:publish --tag=nats-config
```

## Configuration

Configure your NATS connection in `config/nats.php` or via environment variables:

```env
NATS_HOST=localhost
NATS_PORT=4222
NATS_USER=
NATS_PASSWORD=
NATS_TOKEN=
```

### Multiple Connections

```php
// config/nats.php
'connections' => [
    'default' => [
        'host' => env('NATS_HOST', 'localhost'),
        'port' => (int) env('NATS_PORT', 4222),
    ],
    'secondary' => [
        'host' => env('NATS_SECONDARY_HOST', 'nats-2.example.com'),
        'port' => 4222,
    ],
],
```

## Quick Start

### Publishing Messages

```php
use LaravelNats\Laravel\Facades\Nats;

// Publish with array payload (auto-serialized to JSON)
Nats::publish('orders.created', [
    'order_id' => 123,
    'customer' => 'John Doe',
]);

// Publish to a specific connection
Nats::connection('secondary')->publish('events', $data);
```

### Subscribing to Messages

```php
use LaravelNats\Laravel\Facades\Nats;

// Subscribe to a subject
Nats::subscribe('orders.*', function ($message) {
    $payload = $message->getDecodedPayload();
    logger('Order received', $payload);
});

// Process incoming messages
Nats::process(1.0); // Wait up to 1 second for messages
```

### Queue Groups (Load Balancing)

```php
// Messages are distributed across subscribers in the same queue group
Nats::subscribe('orders.process', function ($message) {
    // Process order
}, 'order-workers');
```

### Request/Reply

```php
use LaravelNats\Laravel\Facades\Nats;

// Send a request and wait for a reply
$reply = Nats::request('users.get', ['id' => 42], timeout: 5.0);
$user = $reply->getDecodedPayload();
```

### Wildcards

NATS supports two wildcards for subscriptions:

- `*` matches a single token: `orders.*` matches `orders.created`, `orders.updated`
- `>` matches one or more tokens: `orders.>` matches `orders.created`, `orders.us.created`

```php
Nats::subscribe('logs.>', function ($message) {
    // Receives all log messages
});
```

## Queue Driver

Use NATS as a Laravel queue backend:

### Configuration

Add the NATS connection to your `config/queue.php`:

```php
'connections' => [
    // ... other connections ...

    'nats' => [
        'driver' => 'nats',
        'host' => env('NATS_HOST', 'localhost'),
        'port' => env('NATS_PORT', 4222),
        'user' => env('NATS_USER'),
        'password' => env('NATS_PASSWORD'),
        'token' => env('NATS_TOKEN'),
        'queue' => env('NATS_QUEUE', 'default'),
        'retry_after' => 60,
    ],
],
```

### Usage

```php
// Dispatch a job to the NATS queue
dispatch(new ProcessOrder($order))->onConnection('nats');

// Or set NATS as default in .env
// QUEUE_CONNECTION=nats
```

### Job Lifecycle & Retry

Configure retry behavior on your jobs:

```php
class ProcessOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 5;           // Maximum attempts
    public $backoff = [10, 30, 60]; // Linear backoff: 10s, 30s, 60s delays
    
    // Or use exponential backoff
    // public $backoff = 60;     // Fixed 60s delay between retries
}
```

The queue driver supports:
- **Configurable max attempts** (`$tries` or `maxTries`)
- **Linear backoff** (array of delays)
- **Fixed delay** (integer delay)
- **Retry deadlines** (`retryUntil`)
- **Exception limits** (`maxExceptions`)

### Failed Jobs

Failed jobs are automatically stored in Laravel's `failed_jobs` table when:
- Maximum attempts are exceeded
- An exception is thrown during job execution
- The job explicitly calls `$this->fail($exception)`

#### Handling Failed Jobs

```php
class ProcessOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public function failed(Throwable $exception): void
    {
        // Handle the failure
        logger()->error('Order processing failed', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

#### Dead Letter Queue (DLQ)

Configure a Dead Letter Queue to route failed jobs to a separate NATS subject:

```php
// config/queue.php
'nats' => [
    'driver' => 'nats',
    'host' => env('NATS_HOST', 'localhost'),
    'port' => env('NATS_PORT', 4222),
    'queue' => env('NATS_QUEUE', 'default'),
    'retry_after' => 60,
    'dead_letter_queue' => env('NATS_QUEUE_DLQ', 'failed'), // Optional
],
```

When a job fails, it will be:
1. Stored in the `failed_jobs` database table
2. Routed to the DLQ subject (if configured) with enhanced metadata:
   - Original queue name
   - Failure exception message
   - Failure timestamp
   - Stack trace

You can subscribe to the DLQ to process failed jobs:

```php
use LaravelNats\Laravel\Facades\Nats;

Nats::subscribe('laravel.queue.failed', function ($message) {
    $payload = $message->getDecodedPayload();
    
    // Process failed job
    logger()->error('Failed job received', [
        'original_queue' => $payload['original_queue'],
        'failure_message' => $payload['failure_message'],
    ]);
});
```

#### Delayed Jobs (JetStream)

Delayed jobs require **JetStream** to be enabled on your NATS server. When enabled, jobs dispatched with `later()` are stored in a JetStream stream and delivered to the queue when due.

**1. Enable delayed jobs** in your queue connection or in `config/nats.php`:

```php
// config/queue.php – enable on the connection
'nats' => [
    'driver' => 'nats',
    'host' => env('NATS_HOST', 'localhost'),
    'port' => env('NATS_PORT', 4222),
    'queue' => env('NATS_QUEUE', 'default'),
    'retry_after' => 60,
    'delayed' => [
        'enabled' => true,
        'stream' => env('NATS_QUEUE_DELAYED_STREAM', 'laravel_delayed'),
        'subject_prefix' => env('NATS_QUEUE_DELAYED_SUBJECT_PREFIX', 'laravel.delayed.'),
        'consumer' => env('NATS_QUEUE_DELAYED_CONSUMER', 'laravel_delayed_worker'),
    ],
],
```

Defaults for `stream`, `subject_prefix`, and `consumer` are also defined under `queue.delayed` in `config/nats.php` (or via `NATS_QUEUE_DELAYED_*` env vars).

**2. Use `later()`** to schedule jobs:

```php
use Illuminate\Support\Facades\Queue;

// Run job in 5 minutes
Queue::connection('nats')->later(now()->addMinutes(5), new SendReminder($user));

// Or with a delay in seconds
Queue::connection('nats')->later(60, new ProcessOrder($order));
```

When delayed is enabled, the connector automatically ensures the JetStream delay stream and durable consumer exist at connect time. A delay processor (or worker) consumes from the delay stream and pushes jobs to the main queue when they are due.

### Running Queue Workers

Use Laravel's standard queue worker commands:

```bash
# Start a queue worker
php artisan queue:work nats

# Process jobs from a specific queue
php artisan queue:work nats --queue=high,default

# Set maximum job attempts
php artisan queue:work nats --tries=3

# Set job timeout
php artisan queue:work nats --timeout=60

# Set memory limit
php artisan queue:work nats --memory=128
```

**Supported Worker Options:**
- `--queue` - Specify which queues to process
- `--tries` - Maximum number of attempts for a job
- `--timeout` - Seconds a child process can run
- `--memory` - Memory limit in megabytes
- `--sleep` - Seconds to sleep when no job available
- `--once` - Process a single job and exit

### Current Limitations

- **Delayed jobs:** Require JetStream; enable via `queue.delayed.enabled` (see [Delayed Jobs (JetStream)](#delayed-jobs-jetstream)).
- **Queue size:** Returns 0 (NATS Core doesn't track queue size)
- **Priority queues:** Not supported in NATS Core

## Authentication

### Username/Password

```env
NATS_USER=myuser
NATS_PASSWORD=mypassword
```

### Token

```env
NATS_TOKEN=my-secret-token
```

## Testing

This package uses [Pest PHP](https://pestphp.com/) for testing.

### Running Tests

```bash
# Start the NATS server (requires Docker)
docker-compose up -d

# Run all tests
composer test

# Run with coverage
composer test:coverage
```

### Static Analysis

```bash
composer analyse
```

### Code Style

```bash
# Check code style
composer format:check

# Fix code style
composer format
```

## Troubleshooting

### Connection Refused

**Error:** `Connection to localhost:4222 refused`

**Solution:** Ensure the NATS server is running:

```bash
# Using Docker
docker run -d --name nats -p 4222:4222 -p 8222:8222 nats:2.10

# Or with Docker Compose
docker-compose up -d
```

### Authentication Failed

**Error:** `Authorization Violation`

**Solutions:**
1. Verify credentials in your `.env` file match the NATS server configuration
2. Check if using token auth vs. username/password auth
3. Ensure the NATS server is configured for the authentication method you're using

### Queue Jobs Not Processing

**Possible causes:**

1. **Worker not running:** Start the queue worker:
   ```bash
   php artisan queue:work nats
   ```

2. **Wrong queue name:** Ensure your job is dispatched to the correct queue:
   ```php
   dispatch(new MyJob())->onQueue('high');
   ```
   Then process that queue:
   ```bash
   php artisan queue:work nats --queue=high
   ```

3. **NATS connection issues:** Check NATS server logs for errors

### Message Size Limits

NATS has a default maximum message size of 1MB. For larger payloads:

1. Store the data externally (S3, database) and pass a reference
2. Configure NATS server with a higher `max_payload` setting

## API Stability

This package follows [Semantic Versioning](https://semver.org/). After v1.0.0:

- **Stable API:** Classes in the `LaravelNats\Laravel` namespace
  - `Nats` facade
  - `NatsManager`
  - `NatsQueue`, `NatsJob`, `NatsConnector`
  - Configuration structure

- **Internal API:** Classes in the `LaravelNats\Core` namespace
  - May change in minor versions
  - Use the facade for stability

## JetStream Support

JetStream is NATS's persistence and streaming layer. This package provides access to JetStream functionality.

### Prerequisites

Ensure your NATS server has JetStream enabled:

```bash
# Docker example
docker run -d --name nats -p 4222:4222 -p 8222:8222 nats:2.10 --jetstream
```

### Basic Usage

```php
use LaravelNats\Laravel\Facades\Nats;

// Get JetStream client
$js = Nats::jetstream();

// Check if JetStream is available
if ($js->isAvailable()) {
    // Use JetStream features
}
```

### Configuration

Configure JetStream in `config/nats.php`:

```php
'jetstream' => [
    'domain' => env('NATS_JETSTREAM_DOMAIN'),  // Optional: for multi-tenancy
    'timeout' => (float) env('NATS_JETSTREAM_TIMEOUT', 5.0),
],
```

### Domain Support

For multi-tenant setups, you can use JetStream domains:

```php
$js = Nats::jetstream(null, new \LaravelNats\Core\JetStream\JetStreamConfig('my-domain'));
```

### Stream Management

Create and manage JetStream streams:

```php
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Laravel\Facades\Nats;

$js = Nats::jetstream();

// Create a stream
$config = new StreamConfig('my-stream', ['events.>'])
    ->withDescription('Event stream')
    ->withMaxMessages(10000)
    ->withMaxBytes(104857600) // 100MB
    ->withStorage(StreamConfig::STORAGE_FILE);

$info = $js->createStream($config);

// Get stream information
$info = $js->getStreamInfo('my-stream');
echo $info->getMessageCount(); // Number of messages
echo $info->getByteCount();    // Total bytes stored

// Update stream configuration
$updated = $config->withMaxMessages(20000);
$info = $js->updateStream($updated);

// Purge all messages
$js->purgeStream('my-stream');

// Delete stream
$js->deleteStream('my-stream');
```

### Stream Operations

Get and delete individual messages:

```php
// Get message by sequence number
$message = $js->getMessage('my-stream', 123);

// Delete message by sequence number
$js->deleteMessage('my-stream', 123);
```

### Consumer Management

Create and manage durable consumers on a stream:

```php
use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Laravel\Facades\Nats;

$js = Nats::jetstream();

// Create a durable consumer
$config = (new ConsumerConfig('my-consumer'))
    ->withFilterSubject('events.>')
    ->withDeliverPolicy(ConsumerConfig::DELIVER_NEW)
    ->withAckPolicy(ConsumerConfig::ACK_EXPLICIT);

$info = $js->createConsumer('my-stream', 'my-consumer', $config);

// Get consumer information
$info = $js->getConsumerInfo('my-stream', 'my-consumer');
echo $info->getNumPending();   // Messages awaiting delivery
echo $info->getNumAckPending(); // Messages awaiting ack

// List consumers (paged)
$result = $js->listConsumers('my-stream', offset: 0);
foreach ($result['consumers'] as $consumer) {
    echo $consumer->getName();
}
// $result has: total, offset, limit, consumers

// Delete a consumer
$js->deleteConsumer('my-stream', 'my-consumer');
```

### Pull consumer and acknowledgements

Consume messages from a pull consumer and acknowledge them:

```php
use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\JetStreamConsumedMessage;
use LaravelNats\Laravel\Facades\Nats;

$js = Nats::jetstream();

// Create a pull consumer (no deliver_subject) with explicit ack
$config = (new ConsumerConfig('my-consumer'))
    ->withAckPolicy(ConsumerConfig::ACK_EXPLICIT)
    ->withDeliverPolicy(ConsumerConfig::DELIVER_ALL);
$js->createConsumer('my-stream', 'my-consumer', $config);

// Fetch next message (returns null when no_wait and no message)
$msg = $js->fetchNextMessage('my-stream', 'my-consumer', timeout: 5.0, noWait: true);

if ($msg instanceof JetStreamConsumedMessage) {
    echo $msg->getPayload();
    $js->ack($msg);           // Positive ack
    // $js->nak($msg);        // Redeliver
    // $js->nak($msg, 30_000_000_000);  // Redeliver after 30s (nanoseconds)
    // $js->term($msg);       // Terminate (do not redeliver)
    // $js->inProgress($msg); // Extend ack wait (work in progress)
}
```

### Artisan Commands (JetStream)

Manage streams and consumers from the CLI:

```bash
# Streams
php artisan nats:stream:list [--connection=] [--offset=0]
php artisan nats:stream:info {stream} [--connection=]
php artisan nats:stream:create {name} {subjects*} [--connection=] [--description=] [--storage=file|memory] [--retention=limits|interest|workqueue]
php artisan nats:stream:update {stream} [--connection=] [--description=] [--storage=] [--retention=] [--max-messages=] [--max-bytes=] [--max-age=]
php artisan nats:stream:purge {stream} [--connection=] [--force]
php artisan nats:stream:delete {stream} [--connection=] [--force]

# Consumers
php artisan nats:consumer:list {stream} [--connection=] [--offset=0]
php artisan nats:consumer:info {stream} {consumer} [--connection=]
php artisan nats:consumer:create {stream} {name} [--connection=] [--filter-subject=] [--deliver-policy=all|last|new] [--ack-policy=none|all|explicit]
php artisan nats:consumer:delete {stream} {consumer} [--connection=] [--force]

# JetStream account
php artisan nats:jetstream:status [--connection=] [--json]
```

Use `--connection=` to target a non-default NATS connection from `config/nats.php`.

## Roadmap

This package is under active development. Current status:

- ✅ **Phase 1:** Core Messaging (Publish, Subscribe, Request/Reply)
- ✅ **Phase 1:** Laravel Integration (ServiceProvider, Facade, Config)
- ✅ **Phase 2:** Laravel Queue Driver (Complete)
  - ✅ Milestone 2.1: Queue Driver Foundation
  - ✅ Milestone 2.2: Delayed Jobs (JetStream)
  - ✅ Milestone 2.3: Job Lifecycle & Retry
  - ✅ Milestone 2.4: Failed Jobs & DLQ
  - ✅ Milestone 2.5: Queue Worker Compatibility
  - ✅ Milestone 2.6: Queue Driver Stabilization
- ✅ **Phase 3:** JetStream Support (Complete)
  - ✅ Milestone 3.1: JetStream Connection
  - ✅ Milestone 3.2: Stream Management
  - ✅ Milestone 3.3: Consumer Management
  - ✅ Milestone 3.4: Acknowledgement System
  - ✅ Milestone 3.5: Artisan Commands (stream purge/update, jetstream:status, getAccountInfo)
- 🔲 **Phase 4:** Worker & Runtime
- 🔲 **Phase 5:** Observability & Debugging
- 🔲 **Phase 6:** Reliability & Resilience

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

