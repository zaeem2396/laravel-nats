# Laravel NATS

[![Tests](https://github.com/YOUR_USERNAME/laravel-nats/actions/workflows/tests.yml/badge.svg)](https://github.com/YOUR_USERNAME/laravel-nats/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/YOUR_USERNAME/laravel-nats/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/YOUR_USERNAME/laravel-nats/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/YOUR_USERNAME/laravel-nats/actions/workflows/code-style.yml/badge.svg)](https://github.com/YOUR_USERNAME/laravel-nats/actions/workflows/code-style.yml)

A native NATS integration for Laravel that feels like home. Publish, subscribe, and request/reply with a familiar, expressive API.

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x
- NATS Server 2.x

## Installation

```bash
composer require your-vendor/laravel-nats
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
- 🔲 **Phase 2:** Laravel Queue Driver
- 🔲 **Phase 3:** JetStream Support
- 🔲 **Phase 4:** Worker & Runtime
- 🔲 **Phase 5:** Observability & Debugging
- 🔲 **Phase 6:** Reliability & Resilience

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

