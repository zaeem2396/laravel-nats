# Laravel NATS v2 - Developer guide

The v2 stack is a **Laravel wrapper** around [basis-company/nats](https://packagist.org/packages/basis-company/nats): configuration, facades, and a JSON envelope are provided here; the wire protocol is handled by that client.

**Legacy:** `Nats` + `Core\Client`. **v2:** `NatsV2` + `ConnectionManager` + `Basis\Nats\Client`.

## Contents

1. Config
2. Publish
3. Container
4. Connections
5. Headers & errors
6. Ops
7. Dual stack
8. Testing
9. Migration strategy
10. Subscribe (v2.1)

## Config

`php artisan vendor:publish --tag=nats-config` writes `nats_basis.php`; defaults are merged from the package.

| `NATS_HOST` / `NATS_PORT` | TCP endpoint |
| `NATS_USER` / `NATS_PASS` | User auth |
| `NATS_TOKEN` | Token |
| `NATS_ENVELOPE_VERSION` | Envelope `version` field |
| `NATS_BASIS_LOGGING` | When `true`, pass Laravel’s log channel to `Basis\Nats\Client` (wire-level traces from the dependency) |
| `NATS_BASIS_LOG_CHANNEL` | Laravel channel name (default `stack`) when logging is enabled |

TLS file paths: `NATS_TLS_KEY`, `NATS_TLS_CERT`, `NATS_TLS_CA`.

## Publish

`NatsV2::publish(subject, payload, headers, connection)`.

Envelope JSON: `id` (uuid), `type` (subject), `version`, `data` (payload).

```php
NatsV2::publish('a.b', ['k' => 1]);
```

## Container

`ConnectionManager`, `NatsPublisher`, `nats.v2` gateway registered in `NatsServiceProvider`.

## Connections

Configure `nats_basis.connections.{name}`.

`NatsV2::disconnect` / `disconnectAll` clear cached clients.

## Headers

String values; sent as HPUB when non-empty.

## Errors

`PublishException` wraps publish/JSON failures.

## Ops

Tune `timeout`, protect secrets, use process managers for workers.

## Dual stack

Legacy publish = raw JSON body; v2 = envelope. Migrate per subject.

## Migration strategy

Legacy **`Nats`** / **`NatsManager`** / **`Core\Client`** are **soft-deprecated** for new work as of 2.0.0; **`NatsV2`** is the supported path for new publishers. Subscribe, queue, and JetStream stay on the legacy facade until **v2.2+**. **No silent removals** in v2.x minors.

**Full policy, config mapping (`nats.php` ↔ `nats_basis.php`), facade table, and per-minor testing checklist:** [MIGRATION.md](MIGRATION.md).

## Testing

Unit tests for envelope and provider; CI uses Docker NATS.

## Subscribe (v2.1)

`NatsV2::subscribe($subject, callable(InboundMessage): void, $queueGroup = null, $connection = null)` wraps `Basis\Nats\Client` subscribe / subscribeQueue. You must call `NatsV2::process($connection, $timeout)` in a loop (or use `php artisan nats:v2:listen`). Full reference: [SUBSCRIBER.md](SUBSCRIBER.md).

## See also

[Migration](MIGRATION.md) - [Subscriber (v2.1)](SUBSCRIBER.md)

### Security

Do not place secrets inside `data`; redact logs.

*Migration:* [MIGRATION.md](MIGRATION.md)
