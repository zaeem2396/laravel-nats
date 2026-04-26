# Laravel NATS

[![Tests](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml)

Laravel wrapper for NATS with two tracks:

- **Recommended:** `NatsV2` on [`basis-company/nats`](https://github.com/basis-company/nats.php)
- **Supported legacy track:** `Nats` facade + legacy queue/JetStream APIs

## Quick Navigation

- [What to use](#what-to-use)
- [Install](#install)
- [5-minute quickstart (natsv2)](#5-minute-quickstart-natsv2)
- [Documentation index](#documentation-index)
- [Queue usage](#queue-usage)
- [Security and production checks](#security-and-production-checks)
- [Testing and quality checks](#testing-and-quality-checks)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

## What To Use

### NatsV2 (recommended)

Use `NatsV2` for new code:

- `NatsV2::publish` / `NatsV2::subscribe`
- basis-client JetStream helpers
- `nats_basis` queue driver
- optional idempotency, observability, and security controls

### Legacy (still supported)

Use legacy `Nats` APIs only when you must keep existing workloads unchanged. Migration guide: [`docs/v2/MIGRATION.md`](docs/v2/MIGRATION.md).

## Install

Requirements:

- PHP 8.2+
- Laravel 10.x / 11.x / 12.x
- NATS Server 2.x

```bash
composer require zaeem2396/laravel-nats
php artisan vendor:publish --tag=nats-config
```

Version map:

- **1.3.0+**: `NatsV2` publish/subscribe foundation
- **1.4.0+**: basis JetStream + `nats_basis` queue + idempotency + observability
- **1.5.0+**: config validation, TLS production guard, optional ACL
- **1.5.1**: documentation refresh (navigation + cross-links)

Roadmap: [`docs/ROADMAP_V2_NATSPHP.md`](docs/ROADMAP_V2_NATSPHP.md)

## 5-Minute Quickstart (NatsV2)

### 1) Configure env

```env
NATS_HOST=127.0.0.1
NATS_PORT=4222
NATS_USER=
NATS_PASS=
NATS_TOKEN=
NATS_BASIS_VALIDATE_CONFIG=false
NATS_TLS_REQUIRE_IN_PRODUCTION=false
```

### 2) Publish

```php
use LaravelNats\Laravel\Facades\NatsV2;

NatsV2::publish('orders.created', ['order_id' => 123], ['X-Request-Id' => 'req-1']);
```

### 3) Subscribe

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

NatsV2::subscribe('orders.created', function (InboundMessage $message): void {
    $payload = $message->envelopePayload();
    logger()->info('Order received', (array) $payload);
});

while (true) {
    NatsV2::process(null, 1.0);
}
```

## Documentation Index

### By use case

| If you want to... | Go here |
|---|---|
| Publish/subscribe on v2 quickly | [`docs/v2/GUIDE.md`](docs/v2/GUIDE.md) |
| Build long-running consumers | [`docs/v2/SUBSCRIBER.md`](docs/v2/SUBSCRIBER.md) |
| Run queue workers on basis client | [`docs/v2/QUEUE.md`](docs/v2/QUEUE.md) |
| Work with JetStream streams/consumers | [`docs/v2/JETSTREAM.md`](docs/v2/JETSTREAM.md) |
| Harden config and subject access | [`docs/v2/SECURITY.md`](docs/v2/SECURITY.md) |
| Move from legacy APIs to v2 | [`docs/v2/MIGRATION.md`](docs/v2/MIGRATION.md) |

### Start here

- [`docs/v2/README.md`](docs/v2/README.md)
- [`docs/v2/GUIDE.md`](docs/v2/GUIDE.md)
- [`docs/v2/FAQ.md`](docs/v2/FAQ.md)

### Core feature guides

- Subscriber: [`docs/v2/SUBSCRIBER.md`](docs/v2/SUBSCRIBER.md)
- JetStream (v2): [`docs/v2/JETSTREAM.md`](docs/v2/JETSTREAM.md)
- Queue (`nats_basis`): [`docs/v2/QUEUE.md`](docs/v2/QUEUE.md)
- Migration: [`docs/v2/MIGRATION.md`](docs/v2/MIGRATION.md)

### Production and operations

- Security + ACL: [`docs/v2/SECURITY.md`](docs/v2/SECURITY.md)
- Observability: [`docs/v2/OBSERVABILITY.md`](docs/v2/OBSERVABILITY.md)
- Correlation headers: [`docs/v2/CORRELATION.md`](docs/v2/CORRELATION.md)
- Idempotency: [`docs/v2/IDEMPOTENCY.md`](docs/v2/IDEMPOTENCY.md)
- Client protocol features: [`docs/v2/CLIENT_FEATURES.md`](docs/v2/CLIENT_FEATURES.md)

### Examples

- Example index: [`docs/v2/examples/README.md`](docs/v2/examples/README.md)

## Queue Usage

Two queue drivers are available:

- **`nats`**: legacy driver on `LaravelNats\Core\Client`
- **`nats_basis`**: recommended driver on `Basis\Nats\Client`

Use with Laravel worker:

```bash
php artisan queue:work nats_basis --queue=default --tries=3
```

Queue guide: [`docs/v2/QUEUE.md`](docs/v2/QUEUE.md)

## Security And Production Checks

For hardened environments (1.5.0+):

- optional boot validation: `NATS_BASIS_VALIDATE_CONFIG=true`
- optional TLS requirement in production: `NATS_TLS_REQUIRE_IN_PRODUCTION=true`
- optional publish/subscribe ACL: `NATS_ACL_*`
- explicit validator command: `php artisan nats:v2:config:validate`

Full details: [`docs/v2/SECURITY.md`](docs/v2/SECURITY.md)

## Testing And Quality Checks

```bash
# Optional local NATS
docker compose up -d

# Test suite
composer test

# Static analysis
composer analyse

# Style check
composer format:check
```

## Troubleshooting

### Connection refused

Start NATS locally:

```bash
docker compose up -d
```

### Authorization violation

Verify `.env` credentials and server auth mode (`NATS_USER`/`NATS_PASS` vs `NATS_TOKEN`).

### Production TLS/config errors

If you get `NatsConfigurationException`, validate your `nats_basis` connection rows:

```bash
php artisan nats:v2:config:validate
```

Then confirm TLS values in [`docs/v2/SECURITY.md`](docs/v2/SECURITY.md).

## API Stability

Public Laravel-facing APIs are stable under semver. Internal implementation classes (especially under core internals) may evolve.

See release notes: [`CHANGELOG.md`](CHANGELOG.md)

## Contributing

Before opening a PR, run:

```bash
composer test
composer format:check
composer analyse
```

## License

MIT. See [`LICENSE`](LICENSE).
