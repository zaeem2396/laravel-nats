# Roadmap

Package releases follow [Semantic Versioning](https://semver.org/). See [CHANGELOG.md](../CHANGELOG.md) for shipped changes and [GitHub Releases](https://github.com/zaeem2396/laravel-nats/releases) for tags.

## Release mapping (NatsV2 themes)

| Package | NatsV2 theme | Focus |
|---------|--------------|--------|
| 1.3.0+ | v2 foundation | `NatsV2` publish/subscribe on basis-company/nats |
| 1.4.0+ | v2.4–v2.5 | Basis JetStream, `nats_basis` queue, idempotency, observability |
| 1.5.0+ | v2.6 | Config validation, TLS production guard, optional subject ACL |
| 1.6.0+ | v2.7 | Trace context, connection selection, outbox recipe |
| 1.6.1 | — | Test coverage, CI (Pest, PHPStan, Pint), no public API changes |
| 1.6.2 | — | Connection reconnect helpers and transport edge-case fixes |

## Current stable

**v1.6.2** — connection reconnect and transport reliability improvements.

## Upgrade path

- New integrations: use **`NatsV2`** and [`docs/v2/GUIDE.md`](v2/GUIDE.md).
- Legacy **`Nats`** / **`nats`** queue: supported; migrate with [`docs/v2/MIGRATION.md`](v2/MIGRATION.md).

## Contributing

Open issues and pull requests on [GitHub](https://github.com/zaeem2396/laravel-nats). Run `composer ci` before submitting changes.
