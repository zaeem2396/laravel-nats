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

### Current Limitations

- **Delayed jobs:** Not yet supported (requires JetStream, coming in v1.0)
- **Queue size:** Returns 0 (NATS Core doesn't track queue size)

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

## Roadmap

This package is under active development. Current status:

- ✅ **Phase 1:** Core Messaging (Publish, Subscribe, Request/Reply)
- ✅ **Phase 1:** Laravel Integration (ServiceProvider, Facade, Config)
- 🔵 **Phase 2:** Laravel Queue Driver (50% Complete)
  - ✅ Milestone 2.1: Queue Driver Foundation
  - ✅ Milestone 2.3: Job Lifecycle & Retry
  - 🔲 Milestone 2.2: Delayed Jobs (requires JetStream)
- 🔲 **Phase 3:** JetStream Support
- 🔲 **Phase 4:** Worker & Runtime
- 🔲 **Phase 5:** Observability & Debugging
- 🔲 **Phase 6:** Reliability & Resilience

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

