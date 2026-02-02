# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **PHP requirements:** Minimum PHP raised from 8.1 to 8.2 (Pest/PHPUnit compatibility). PHP 8.4 added to CI test matrix (Laravel 10, 11, 12).

### Added

#### Queue Driver - Delayed Jobs (Phase 2 - Milestone 2.2)
- Delayed jobs support using JetStream: enable via `queue.delayed.enabled` in queue connection or `config/nats.php`
- Config `queue.delayed`: `stream`, `subject_prefix`, `consumer` (with `NATS_QUEUE_DELAYED_*` env vars)
- `DelayStreamBootstrap`: ensures JetStream delay stream and durable consumer exist (idempotent)
- `DelayStreamBootstrap::ensureStreamAndConsumer()` for use with an existing JetStream client
- NatsConnector: when delayed enabled, bootstraps delay stream/consumer and passes JetStream + delayed config to NatsQueue
- NatsQueue: optional `jetStream` and `delayedConfig` constructor params for `later()` (JetStream path)
- README: Delayed Jobs (JetStream) section and roadmap update

#### JetStream Support (Phase 3 - Milestone 3.5 Artisan Commands)
- `JetStreamClient::listStreams()` - List streams (paged) via STREAM.LIST API
- Artisan commands: `nats:stream:list`, `nats:stream:info`, `nats:stream:create`, `nats:stream:delete`
- Artisan commands: `nats:consumer:list`, `nats:consumer:info`, `nats:consumer:create`, `nats:consumer:delete`
- Commands support `--connection=` for non-default NATS connection
- README Artisan Commands section and roadmap update

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

[Unreleased]: https://github.com/zaeem2396/laravel-nats/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/zaeem2396/laravel-nats/releases/tag/v1.0.0

