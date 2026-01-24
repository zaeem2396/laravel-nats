# Laravel NATS

[![Tests](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml)

A native NATS integration for Laravel that feels like home. Publish, subscribe, and request/reply with a familiar, expressive API.

## Requirements

- PHP 8.1+
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

- **Delayed jobs:** Not yet supported (requires JetStream, coming in v1.0)
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

## Roadmap

This package is under active development. Current status:

- ✅ **Phase 1:** Core Messaging (Publish, Subscribe, Request/Reply)
- ✅ **Phase 1:** Laravel Integration (ServiceProvider, Facade, Config)
- 🔵 **Phase 2:** Laravel Queue Driver (90% Complete)
  - ✅ Milestone 2.1: Queue Driver Foundation
  - ✅ Milestone 2.3: Job Lifecycle & Retry
  - ✅ Milestone 2.4: Failed Jobs & DLQ
  - ✅ Milestone 2.5: Queue Worker Compatibility
  - 🔵 Milestone 2.6: Queue Driver Stabilization
  - 🔲 Milestone 2.2: Delayed Jobs (requires JetStream)
- 🔲 **Phase 3:** JetStream Support
- 🔲 **Phase 4:** Worker & Runtime
- 🔲 **Phase 5:** Observability & Debugging
- 🔲 **Phase 6:** Reliability & Resilience

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

