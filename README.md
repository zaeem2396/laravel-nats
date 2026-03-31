# Laravel NATS

[![Tests](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml/badge.svg)](https://github.com/zaeem2396/laravel-nats/actions/workflows/code-style.yml)

Native NATS integration for Laravel: publish, subscribe, request/reply, queue driver, and JetStream support.

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- NATS Server 2.x

## Installation

```bash
composer require zaeem2396/laravel-nats
```

Version highlights (pick the range that matches the features you need):

- **v1.1.1+** — Phase 4 queue/worker commands (`nats:work`, `nats:consume`).
- **v1.3.0+** — `NatsV2::publish` / `NatsV2::subscribe`, `ConnectionManager`, JSON envelope (`config/nats_basis.php`), `InboundMessage`, `nats:v2:listen`.
- **v1.4.0+** — `NatsV2::jetstream()`, JetStream helpers, `nats_basis` queue driver, idempotency and observability options.
- **v1.5.0+** — Optional config validation on boot, TLS production expectations, optional subject ACL, `php artisan nats:v2:config:validate`.

The service provider is auto-discovered.

## Setup

Publish configuration (includes `nats` and `nats_basis`):

```bash
php artisan vendor:publish --tag=nats-config
```

Environment variables (adjust for your deployment):

```env
NATS_HOST=localhost
NATS_PORT=4222
NATS_USER=
NATS_PASSWORD=
NATS_TOKEN=
```

For the v2 basis client (`NatsV2`), the password key in `config/nats_basis.php` maps to **`NATS_PASS`** (not `NATS_PASSWORD`).

```env
# NatsV2 / nats_basis
# NATS_PASS=
```

### Local NATS with Docker

From the package root when developing against this repository:

```bash
docker compose up -d
```

Uses the default client port **4222** (see `docker-compose.yml` in this repo).

Release history: [CHANGELOG.md](CHANGELOG.md).  
License: [LICENSE](LICENSE).
