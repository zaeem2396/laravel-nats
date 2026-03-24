# v2 migration strategy & upgrade guide

## Dual stack (backward compatibility)

Legacy **`Nats`** facade, **`NatsManager`**, **`LaravelNats\Core\Client`**, the **queue driver**, and **JetStream** stay fully usable alongside **`NatsV2`** while you migrate. You can move **per subject** or **per service** on your own schedule.

### Subscriber (`NatsV2::subscribe`, package 1.3.0+)

New code can use **`NatsV2::subscribe`** (basis client) with **`InboundMessage`** instead of legacy `Nats::subscribe` + `MessageInterface`. You still run a **`process()`** loop (or `nats:v2:listen`). See [SUBSCRIBER.md](SUBSCRIBER.md).

## Deprecation policy (package 1.3.0+)

| Rule | Detail |
|------|--------|
| **Soft deprecation** | `Nats`, `NatsManager`, and `Core\Client` are tagged `@deprecated` for **new** integrations as of **1.3.0**. |
| **Publish** | Prefer **`NatsV2::publish`** (JSON envelope + [basis-company/nats](https://github.com/basis-company/nats.php)). |
| **Subscribe (basis client)** | Prefer **`NatsV2::subscribe`** + **`process()`** / **`nats:v2:listen`** since **1.3.0** ([SUBSCRIBER.md](SUBSCRIBER.md)). Legacy **`Nats::subscribe`** remains supported. |
| **Request/reply, queue, JetStream** | Legacy **`Nats`** until a **future release** documents basis-client parity for those areas. |
| **Minors** | **No silent removals** in upcoming minor releases. Removals only in a **future major** after parity and notice. |

## Config mapping: `config/nats.php` ↔ `config/nats_basis.php`

Both are merged when the package boots; run `php artisan vendor:publish --tag=nats-config` to materialize files on disk. Many env vars are shared for the default connection.

| Concern | Legacy `nats.php` | v2 `nats_basis.php` |
|--------|-------------------|---------------------|
| Default connection name | `default` ← `NATS_CONNECTION` | `default` ← `NATS_BASIS_CONNECTION` (falls back to `NATS_CONNECTION`) |
| Host / port | `connections.*.host` / `port` | Same keys; `NATS_HOST`, `NATS_PORT` |
| User / password | `user`, `password` (`NATS_PASSWORD`) | `user`, `pass` - env **`NATS_PASS`** (not `NATS_PASSWORD`) |
| Token | `token` | `token` |
| JWT / NKey | (extend config as needed) | `jwt`, `nkey` + `NATS_JWT`, `NATS_NKEY` |
| Timeout | `timeout` (float seconds) | `timeout` (float) |
| Ping | `ping_interval` (float) | `pingInterval` (int, seconds) |
| TLS | `tls.enabled` + `tls.options` | File paths: `tlsKeyFile`, `tlsCertFile`, `tlsCaFile` + `NATS_TLS_KEY`, `NATS_TLS_CERT`, `NATS_TLS_CA` |
| Envelope schema | - | `envelope_version` / `NATS_ENVELOPE_VERSION` (default `v1`) |
| Debug logging (basis client) | - | `nats_basis.logging` / `NATS_BASIS_LOGGING`, `NATS_BASIS_LOG_CHANNEL` |

**Future unified config:** a later release may merge these into one file; until then, if you use **both** stacks, keep **both** configs consistent for shared connection names.

## Facade and client usage

| Task | Legacy | v2 |
|------|--------|-----|
| Publish (envelope) | - | `NatsV2::publish($subject, $payload, $headers = [], $connection = null)` |
| Publish (raw JSON body) | `Nats::publish(...)` | Migrate consumers, then switch to `NatsV2` |
| Low-level client | `Nats::connection()` → `LaravelNats\Core\Client` | `NatsV2::connection()` → `Basis\Nats\Client` |
| Subscribe (basis stack) | `Nats::subscribe` + `MessageInterface` | **`NatsV2::subscribe`** + **`InboundMessage`** + **`process()`** / **`nats:v2:listen`** (1.3.0+) |
| Request/reply, queue, JetStream | `Nats::…` | Unchanged on legacy until a future parity release |

## `LaravelNats\Core\Client`

Treat as the **legacy wire client** behind the `Nats` facade. **Do not adopt it in new application code**; prefer **`Basis\Nats\Client`** via **`ConnectionManager`** / **`NatsV2`**.

## Consumer expectations (envelope)

v2 publishers send JSON shaped as:

```json
{ "id": "<uuid>", "type": "<subject>", "version": "v1", "data": { ... } }
```

Consumers should read application data from **`data`**. Roll back publishers to `Nats::publish` until all consumers understand the envelope.

## Testing checklist (1.3.0)

- [ ] `NatsV2::publish` reaches NATS; payload matches envelope schema.
- [ ] `config/nats_basis.php` present or merged; auth/TLS env vars match your server.
- [ ] Legacy **queue** / **JetStream** paths still pass your smoke tests if you use them.
- [ ] `NatsV2::subscribe` + `process()` or `nats:v2:listen` receives messages; handlers get `InboundMessage`.
- [ ] Optional: `nats_basis.subscriber` (middleware, events) behaves as documented in [SUBSCRIBER.md](SUBSCRIBER.md).
- [ ] `composer analyse` and test suite green in CI.

### Future parity (JetStream / queue on basis)

- [ ] Re-run integration tests for JetStream and queue when parity is documented.

## See also

- [GUIDE](GUIDE.md) - day-to-day v2 usage (wrapper on **basis-company/nats**)  
- [FAQ](FAQ.md)
