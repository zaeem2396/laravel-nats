# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of Laravel NATS integration

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

