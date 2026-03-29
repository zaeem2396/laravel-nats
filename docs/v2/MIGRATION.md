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
| **Queue (basis client)** | **`nats_basis`** driver from **1.5.0+** ([QUEUE.md](QUEUE.md)); legacy **`nats`** driver unchanged. |
| **Request/reply** | Legacy **`Nats`** until a **future release** documents basis-client parity. |
| **JetStream (basis client)** | **`NatsV2::jetstream()`** and helpers from **1.4.0+** ([JETSTREAM.md](JETSTREAM.md)); legacy **`Nats::jetstream()`** unchanged. |
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
| Queue driver defaults (`nats_basis`) | `config/queue.php` per connection | `nats_basis.queue.*` / `NATS_BASIS_QUEUE_*` (includes optional **`max_in_flight`**) ([QUEUE.md](QUEUE.md)) |
| Idempotency (1.6.0+) | — | `nats_basis.idempotency.*` / `NATS_IDEMPOTENCY_*`; bind **`IdempotencyStoreContract`** for custom stores ([IDEMPOTENCY.md](IDEMPOTENCY.md)) |
| Observability (1.7.0+) | — | `nats_basis.observability.*` / `NATS_OBSERVABILITY_*`, `NATS_REDACT_KEY_SUBSTRINGS`; **`NatsMetricsContract`**, **`nats:ping`** ([OBSERVABILITY.md](OBSERVABILITY.md)) |

**Future unified config:** a later release may merge these into one file; until then, if you use **both** stacks, keep **both** configs consistent for shared connection names.

## Facade and client usage

| Task | Legacy | v2 |
|------|--------|-----|
| Publish (envelope) | - | `NatsV2::publish($subject, $payload, $headers = [], $connection = null)` |
| Publish (raw JSON body) | `Nats::publish(...)` | Migrate consumers, then switch to `NatsV2` |
| Low-level client | `Nats::connection()` → `LaravelNats\Core\Client` | `NatsV2::connection()` → `Basis\Nats\Client` |
| Subscribe (basis stack) | `Nats::subscribe` + `MessageInterface` | **`NatsV2::subscribe`** + **`InboundMessage`** + **`process()`** / **`nats:v2:listen`** (1.3.0+) |
| JetStream (streams, pull, JS publish) | `Nats::jetstream()` | **`NatsV2::jetstream()`**, **`jetStreamPublish`**, **`jetStreamPull`**, presets (**1.4.0+**, [JETSTREAM.md](JETSTREAM.md)) |
| Request/reply, queue | `Nats::…` | Unchanged on legacy until a future parity release |

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

## Testing checklist (1.4.0) - NatsV2 JetStream

- [ ] `php artisan nats:v2:jetstream:info` returns account JSON against a JetStream-enabled server.
- [ ] `NatsV2::jetStreamPublish` lands messages in a stream that captures the subject (or provision a preset first).
- [ ] `NatsV2::jetStreamPull` (or the `pull` Artisan command) receives and **acks** messages for a durable consumer.

### Queue on the basis client (1.5.0+)

- [ ] Add a **`nats_basis`** connection in `config/queue.php` and set `QUEUE_CONNECTION` (or migrate workers gradually).
- [ ] Confirm `config/nats_basis.php` connections match your NATS deployment (same as `NatsV2`).
- [ ] Run `php artisan queue:work nats_basis` (or your connection name); verify retries, `failed_jobs`, and optional DLQ subject.
- [ ] See [QUEUE.md](QUEUE.md) for env keys and Supervisor example.

## See also

- [GUIDE](GUIDE.md) - day-to-day v2 usage (wrapper on **basis-company/nats**)  
- [FAQ](FAQ.md)
