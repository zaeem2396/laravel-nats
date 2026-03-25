# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- (none)

### Removed

- (none)

## [1.4.0] - 2026-03-24

### Added

- **NatsV2 JetStream (basis-company/nats):** `BasisJetStreamManager`, `BasisJetStreamPublisher`, `PullConsumerBatch`, `BasisStreamProvisioner`; `NatsV2::jetstream()`, `jetStreamPublish()`, `jetStreamPull()`, `jetStreamProvisionPreset()`; `nats_basis.jetstream` config (pull defaults, named presets); Artisan `nats:v2:jetstream:info`, `streams`, `pull`, `provision`. See [docs/v2/JETSTREAM.md](docs/v2/JETSTREAM.md).

## [1.3.0] - 2026-03-24

### Added

- **NatsV2 / basis stack** ([basis-company/nats](https://github.com/basis-company/nats.php)): `ConnectionManager`, `NatsPublisher`, envelope `{ id, type, version, data }`, `NatsV2`, `config/nats_basis.php`.
- **Optional PSR-3 logging** for the basis client: `nats_basis.logging` + `NATS_BASIS_LOGGING` / `NATS_BASIS_LOG_CHANNEL` (Laravel log channel).
- **Subscriber stack:** `NatsSubscriberContract`, `NatsBasisSubscriber` wrapping `Basis\Nats\Client::subscribe` / `subscribeQueue`; `InboundMessage` DTO; subject validation; optional inbound middleware pipeline and `NatsInboundMessageReceived` event; `nats_basis.subscriber` config.
- **Artisan** `nats:v2:listen` for long-running console consumers.
- **Docs:** [docs/v2/GUIDE.md](docs/v2/GUIDE.md), [MIGRATION.md](docs/v2/MIGRATION.md), [FAQ.md](docs/v2/FAQ.md), [README](docs/v2/README.md), [SUBSCRIBER.md](docs/v2/SUBSCRIBER.md).

### Changed

- Documentation: migration guide, v2 guide, subscriber docs, and package README aligned with **`NatsV2::subscribe`**; clarified dual-stack deprecation policy and basis config (**`NATS_PASS`**).
- **`composer.json`:** `minimum-stability` is **`stable`** for typical `composer require` installs.
- **`docker-compose.yml`:** header comments reference **`docker compose`** (Compose V2).

### Deprecated

- **Soft deprecation (1.3.0):** `Nats` facade, `NatsManager`, and `LaravelNats\Core\Client` for **new** integrations - use **`NatsV2`** for publish and subscribe on the basis client. Legacy stack remains for request/reply, queue, and JetStream until future parity ([docs/v2/MIGRATION.md](docs/v2/MIGRATION.md)). No removals without notice in upcoming minor releases.

### Removed

- Legacy planning/docs (`ROADMAP.md`, `PROGRESS.md`, `CODE_EXPLANATIONS.md`, `docs/FEATURES.md`).

## [1.1.1] - 2026-02-27

Patch release: Phase 4 Worker & Runtime (nats:work, nats:consume).

### Added

#### Phase 4: Worker & Runtime (Milestone 4.1)
- `nats:work` Artisan command - dedicated NATS queue worker with `--connection`, `--queue`, `--name`, `--pidfile`, `--stop-when-empty`
- PID file support for process managers (Supervisor, systemd); file is removed on shutdown
- Delegates to Laravel's Queue Worker (same job processing as `queue:work nats`) with graceful shutdown and signal handling (SIGTERM, SIGINT, etc.).

#### Phase 4: Worker & Runtime (Milestone 4.2 - Subject-Based Consumer)
- `nats:consume {subject}` Artisan command - subscribe to subject(s) with optional queue group and handler class
- Wildcard subject support (`*` and `>`); multiple subjects via argument and `--subjects=` (comma-separated)
- `--queue=` for queue group (load-balanced consumption); `--handler=` for class implementing `MessageHandlerInterface`. Without `--handler`, messages are printed to console
- `MessageHandlerInterface` - contract for message handlers with `handle(MessageInterface $message): void`; handlers resolved from container (DI)
- Graceful shutdown via SIGTERM/SIGINT when pcntl is available. Unsubscribes before exit.

## [1.1.0] - 2026-02-15

### Added

#### Documentation
- Features overview document (publish, subscribe, queue, JetStream, etc.) - removed in later releases in favor of README and v2 docs
- Feature 2: Subscribe to Subjects - queue groups, unsubscribe, wildcard notes
- Feature 3: Request/Reply Pattern - synchronous request-response, timeout
- Feature 4: Full Laravel Queue Driver - dispatch, retries, backoff, failed jobs, DLQ
- Feature 5: JetStream Support - streams, consumers, acks, StreamConfig
- Feature 6: Delayed Jobs via JetStream - later(), delay(), queue.delayed config
- Feature 7: Multiple Connections - named connections, Nats::connection('name')
- Feature 8: Wildcard Subscriptions - * and > patterns, examples
- Feature 9: Artisan Commands - nats:stream:*, nats:consumer:*, nats:jetstream:status
- Feature 10: Laravel-Native API Design - dispatch, facade, config, queue worker
- README link to features overview (historical)

#### Queue Driver - Delayed Jobs (Phase 2 - Milestone 2.2)
- Delayed jobs support using JetStream: enable via `queue.delayed.enabled` in queue connection or `config/nats.php`
- Config `queue.delayed`: `stream`, `subject_prefix`, `consumer` (with `NATS_QUEUE_DELAYED_*` env vars)
- `DelayStreamBootstrap`: ensures JetStream delay stream and durable consumer exist (idempotent)
- `DelayStreamBootstrap::ensureStreamAndConsumer()` for use with an existing JetStream client
- NatsConnector: when delayed enabled, bootstraps delay stream/consumer and passes JetStream + delayed config to NatsQueue
- NatsQueue: optional `jetStream` and `delayedConfig` constructor params for `later()` (JetStream path)
- README: Delayed Jobs (JetStream) section and documentation update

#### JetStream Support (Phase 3 - Milestone 3.5 Artisan Commands)
- `JetStreamClient::listStreams()` - List streams (paged) via STREAM.LIST API
- Artisan commands: `nats:stream:list`, `nats:stream:info`, `nats:stream:create`, `nats:stream:delete`, `nats:stream:purge`, `nats:stream:update`
- Artisan commands: `nats:consumer:list`, `nats:consumer:info`, `nats:consumer:create`, `nats:consumer:delete`
- Artisan command: `nats:jetstream:status` - JetStream account status and usage (table or `--json`)
- `JetStreamClient::getAccountInfo()` - JetStream account information (memory, storage, streams, consumers, limits) via `$JS.API.INFO`
- Commands support `--connection=` for non-default NATS connection
- README Artisan Commands section and documentation update

#### JetStream Support (Phase 3 - Milestone 3.4 Acknowledgement System)
- `JetStreamConsumedMessage` - Value object for consumed pull messages (ack subject, stream/consumer name, sequences, payload)
- `JetStreamClient::fetchNextMessage()` - Fetch next message from pull consumer (CONSUMER.MSG.NEXT), optional no_wait
- `JetStreamClient::ack()` - Positive acknowledgment (+ACK)
- `JetStreamClient::nak()` - Negative acknowledgment (-NAK), optional delay (nanoseconds)
- `JetStreamClient::term()` - Terminate message (+TERM)
- `JetStreamClient::inProgress()` - Work in progress (+WPI)
- Unit tests for JetStreamConsumedMessage
- Integration tests for pull consumer and ack (AcknowledgementTest)

#### JetStream Support (Phase 3 - Milestone 3.3 Consumer Management)
- `ConsumerConfig` - Value object for consumer configuration (durable name, filter subject, deliver/ack/replay policies, ack_wait, max_deliver, etc.)
- `ConsumerInfo` - Value object for consumer state (stream name, consumer name, config, num_pending, num_ack_pending, num_waiting)
- `JetStreamClient::createConsumer()` - Create durable consumer (CONSUMER.DURABLE.CREATE)
- `JetStreamClient::getConsumerInfo()` - Get consumer information (CONSUMER.INFO)
- `JetStreamClient::deleteConsumer()` - Delete a consumer (CONSUMER.DELETE)
- `JetStreamClient::listConsumers()` - Paged list of consumers (CONSUMER.LIST), returns total, offset, limit, consumers
- Unit tests for ConsumerConfig and ConsumerInfo
- Integration tests for consumer CRUD and listConsumers
- README Consumer Management section and docs

#### JetStream Support (Phase 3 - Milestone 3.1)
- `JetStreamClient` - Core client for interacting with NATS JetStream
- `JetStreamConfig` - Configuration class for JetStream settings (domain, timeout)
- `Client::getJetStream()` - Method to access JetStream client from NATS client
- `NatsManager::jetstream()` - Method to get JetStream client via manager
- `Nats::jetstream()` - Facade method for convenient JetStream access
- JetStream availability detection via `isAvailable()` method
- JetStream API request support with configurable timeout
- Multi-tenant domain support for JetStream
- JetStream configuration section in `config/nats.php`
- Comprehensive unit and integration tests for JetStream connection

### Changed

- **PHP requirements:** Minimum PHP raised from 8.1 to 8.2 (Pest/PHPUnit compatibility). PHP 8.4 added to CI test matrix (Laravel 10, 11, 12).

## [1.0.0] - 2026-01-24

### Added

#### Core Messaging (Phase 1)
- Native NATS client implementation using PHP streams
- Connection management with auto-reconnection support
- Username/password and token-based authentication
- Publish/Subscribe messaging pattern
- Request/Reply pattern with configurable timeout
- Wildcard subscription support (`*` and `>`)
- Queue groups for load balancing
- JSON and PHP serialization options
- Proactive disconnect detection with health checks

#### Laravel Integration
- `NatsServiceProvider` for automatic registration
- `Nats` facade for convenient access
- `NatsManager` for managing multiple connections
- Configuration via `config/nats.php`
- Environment variable support (`NATS_*`)
- Multiple named connections support

#### Queue Driver (Phase 2)
- Full Laravel Queue contract implementation
- `NatsQueue` - Queue backend using NATS subjects
- `NatsJob` - Job wrapper with full lifecycle support
- `NatsConnector` - Queue connection factory
- Job retry with configurable attempts and backoff
- Backoff strategies: fixed, linear, exponential with jitter
- Failed job handling with `failed_jobs` table storage
- Dead Letter Queue (DLQ) support for failed jobs
- Worker compatibility with `queue:work` command
- Support for `--queue`, `--tries`, `--timeout`, `--memory` flags

#### Testing
- Comprehensive test suite with 395+ tests
- Unit tests for all core components
- Integration tests with real NATS server
- Feature tests for Laravel integration
- Worker compatibility tests

### Changed
- N/A (initial release)

### Deprecated
- N/A (initial release)

### Removed
- N/A (initial release)

### Fixed
- N/A (initial release)

### Security
- N/A (initial release)

## Current Limitations

- **Delayed Jobs**: Not supported in NATS Core (requires JetStream, coming in v1.1)
- **Queue Size**: Returns 0 (NATS Core doesn't track queue size)
- **Priority Queues**: Not supported in NATS Core
- **TLS/SSL**: Planned for v1.1

## Upgrade Guide

### From 1.2.0 to 1.3.0

- **NatsV2 (basis client):** Add `config/nats_basis.php` (merged by the service provider; use `php artisan vendor:publish --tag=nats-config` if you publish config). Prefer **`NatsV2::publish`** / **`NatsV2::subscribe`** for new code; see [docs/v2/MIGRATION.md](docs/v2/MIGRATION.md) and [docs/v2/SUBSCRIBER.md](docs/v2/SUBSCRIBER.md).
- **Long-running consumer:** `php artisan nats:v2:listen` (see subscriber docs).
- **Composer:**

```json
{
    "require": {
        "zaeem2396/laravel-nats": "^1.3"
    }
}
```

Run `composer update zaeem2396/laravel-nats` to upgrade.

### From 1.3.0 to 1.4.0

- **JetStream on NatsV2:** See [docs/v2/JETSTREAM.md](docs/v2/JETSTREAM.md) and optional **`nats_basis.jetstream.presets`**. Legacy **`Nats::jetstream()`** is unchanged.

```json
{
    "require": {
        "zaeem2396/laravel-nats": "^1.4"
    }
}
```

Run `composer update zaeem2396/laravel-nats` to upgrade.

### From 1.0.0 to 1.1.0

- **PHP:** Minimum PHP is now 8.2 (was 8.1). Ensure your environment meets this requirement.
- **Delayed jobs:** Enable via `queue.delayed.enabled` in your queue connection config or `config/nats.php`.
- **JetStream:** Use `Nats::jetstream()` for streams, consumers, and acknowledgements. See this package README for examples.

Update `composer.json`:

```json
{
    "require": {
        "zaeem2396/laravel-nats": "^1.1"
    }
}
```

Run `composer update zaeem2396/laravel-nats` to upgrade.

### From Pre-release to 1.0.0

No breaking changes. Simply update your `composer.json`:

```json
{
    "require": {
        "zaeem2396/laravel-nats": "^1.0"
    }
}
```

Run `composer update zaeem2396/laravel-nats` to upgrade.

---

[Unreleased]: https://github.com/zaeem2396/laravel-nats/compare/v1.4.0...HEAD
[1.4.0]: https://github.com/zaeem2396/laravel-nats/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/zaeem2396/laravel-nats/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/zaeem2396/laravel-nats/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/zaeem2396/laravel-nats/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/zaeem2396/laravel-nats/releases/tag/v1.1.0
[1.0.0]: https://github.com/zaeem2396/laravel-nats/releases/tag/v1.0.0

