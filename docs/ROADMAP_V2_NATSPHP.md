# Laravel NATS - plan (basis-company/nats wrapper)

**laravel-nats** is built as a **Laravel wrapper** around **[basis-company/nats](https://github.com/basis-company/nats.php)** (Packagist: `basis-company/nats`). Application code uses this package’s facades, config, and helpers-not a hand-rolled NATS wire protocol.

**Principles:** Laravel-first (container, config, facades), small SOLID classes, production-oriented, avoid overengineering.

**Status labels:** **Completed** · **In progress** · **Planned**

---

## Version v2.0 - Foundation

**Goals:** Core connectivity on `basis-company/nats`; config-driven multi-connection manager; standardized publish envelope; facade entry point. **Out of scope:** queue driver, JetStream, full subscriber runtime (stubs/wiring only as needed).

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Migration | Dual stack: legacy `Nats` / `NatsManager` / queue & JetStream usable alongside `NatsV2` | Completed |
| **M1** Migration | Phased `@deprecated` on legacy entry points; no silent removals in v2.x minors | Completed |
| **M1** Migration | Upgrade guide: config mapping, facades, testing checklist ([docs/v2/MIGRATION.md](v2/MIGRATION.md)) | Completed |
| **M2** Dependency | Composer `basis-company/nats`; PHP/Laravel matrix documented | Completed |
| **M2** Boundaries | Namespaces `Connection/`, `Publisher/`, `Subscriber/` (stubs), `Support/` | Completed |
| **M2** Boundaries | Note when `LaravelNats\Core\Client` is legacy-only ([MIGRATION](v2/MIGRATION.md)) | Completed |
| **M3** Config | `config/nats_basis.php` - `default`, `connections[]`, auth, TLS | Completed |
| **M3** Config | Env mapping (`NATS_*` / `NATS_BASIS_*`), `vendor:publish` tag | Completed |
| **M3** Config | Envelope schema `envelope_version` (default `v1`) | Completed |
| **M4** Connection | `ConnectionManager` - `Basis\Nats\Client`, lazy connect, disconnect/disconnectAll | Completed |
| **M4** Connection | Map config → `Basis\Nats\Configuration` (user/pass, token, JWT, NKey, TLS paths) | Completed |
| **M4** Connection | Optional PSR-3 logger from Laravel `Log` (`nats_basis.logging`) | Completed |
| **M5** Publisher | `MessageEnvelope` `{ id, type, version, data }` | Completed |
| **M5** Publisher | `NatsPublisher` - JSON + headers (HPUB via `Basis\Nats\Message\Payload`) | Completed |
| **M5** Publisher | `NatsV2Gateway` + `NatsV2` facade - `publish`, `connection(?name)` | Completed |
| **M6** Provider | Register `ConnectionManager`, `NatsPublisher`, `nats.v2`; merge config | Completed |
| **M6** Provider | Keep `NatsServiceProvider` for legacy during migration | Completed |
| **M7** Quality | Unit tests (envelope, config); PHPStan for new code | Completed |
| **M7** Quality | README / [docs/v2](v2/README.md) for v2 basis stack | Completed |

---

## Version v2.1 - Subscriber system

**Goals:** First-class subscriptions and inbound handling; Laravel-friendly hooks without a heavy framework inside the package.

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Subscriber API | `NatsSubscriberContract` + `NatsBasisSubscriber` wrapping `Basis\Nats\Client::subscribe` / `subscribeQueue` | Completed |
| **M1** Subscriber API | Subject validation, queue groups, unsubscribe by id / `unsubscribeAll` | Completed |
| **M2** Runtime | Long-running `process()`; `nats:v2:listen` Artisan command | Completed |
| **M2** Runtime | Signal handling (pcntl) in `nats:v2:listen` + docs | Completed |
| **M3** Inbound | `InboundMessage` DTO decoupled from basis `Msg` | Completed |
| **M3** Inbound | Optional v2 envelope decode via `InboundMessage::envelopePayload()` | Completed |
| **M4** DX | Opt-in `NatsInboundMessageReceived` event; middleware class list in config | Completed |
| **M4** DX | Inbound middleware pipeline (`InboundMiddleware`, `LogInboundMiddleware`) | Completed |
| **M5** Defaults | Subject max length; optional warn flag (reserved) | Completed |
| **M6** Observability | Optional `LogInboundMiddleware` for debug traces | Completed |
| **M6** Observability | Request-ID / correlation header convention | Completed |
| **M7** Migration | Docs: [MIGRATION.md](v2/MIGRATION.md), [SUBSCRIBER.md](v2/SUBSCRIBER.md) | Completed |

---

## Version v2.2 - JetStream support

**Goals:** Streams, consumers, publish/consume via `basis-company/nats` - **before** the Laravel queue driver.

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Facade layer | `BasisJetStreamManager` on `Basis\Nats\Api` via `NatsV2::jetstream()` | Completed |
| **M1** Facade layer | Stream/consumer config; reuse `ConnectionManager` | Completed |
| **M2** Publish | JetStream-aware publish; optional v2 envelope | Completed |
| **M3** Consume | Pull consumer helper; batch fetch aligned with basis client | Completed |
| **M3** Consume | Artisan commands only where they add value (thin wrappers) | Completed |
| **M4** Defaults | Starter stream/consumer presets in docs + config | Completed |
| **M5** Migration | Upgrade guide: v1 JetStream vs v2.2; parity checklist | Completed |

---

## Version v2.3 - Queue driver + DLQ

**Goals:** Laravel queue on the basis client **after** JetStream (v2.2). Retry, DLQ, failed-job handling in this release.

**Shipped (package 1.4.0):** **`nats_basis`** driver — **`BasisNatsConnector`**, **`BasisNatsQueue`** (`ConnectionManager` + `Basis\Nats\Client`), same job JSON as legacy **`nats`** for **`queue:work`**, DLQ via **`NatsJob`** + **`publishRawToSubject`**, config **`nats_basis.queue`**, docs [docs/v2/QUEUE.md](v2/QUEUE.md).

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Queue | Connector + queue class using `ConnectionManager` / `Basis\Nats\Client` | Completed |
| **M1** Queue | Push/pop with job payload aligned with legacy `nats` (worker parity) | Completed |
| **M2** Worker | `queue:work` compatibility; retries/backoff | Completed |
| **M2** Worker | Supervisor/systemd docs | Completed (see QUEUE.md) |
| **M3** DLQ | Failed jobs via Laravel defaults | Completed |
| **M3** DLQ | Documented DLQ convention (subject naming) | Completed |
| **M4** Retry | Central retry/backoff in config | Completed (`nats_basis.queue` + connection options) |
| **M4** Backpressure | Light-touch max in-flight (config + counter, per worker process) | Completed |
| **M5** DX | Job serialization contract; helpers for subjects | Completed (legacy JSON documented) |
| **M5** DX | Job middleware compatibility documented | Completed (same job class as legacy driver) |
| **M6** Defaults | Retry/backoff stubs; queue subject conventions | Completed |
| **M7** Migration | Upgrade guide: legacy `nats` queue → `nats_basis` | Completed |

---

## Version v2.4 - Idempotency

**Shipped (package 1.4.0):** optional **`idempotency_key`** on publish (lifted to **`MessageEnvelope`** + **`Nats-Idempotency-Key`** header); **`CacheIdempotencyStore`** + **`IdempotencyStoreContract`**; **`IdempotencyInboundMiddleware`**; **`InboundMessage::idempotencyKey()`**; docs [docs/v2/IDEMPOTENCY.md](v2/IDEMPOTENCY.md).

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Keys | Optional `idempotency_key` in envelope + header mirror | Completed |
| **M1** Store | Pluggable store (`Cache`, Redis, custom) | Completed |
| **M2** Middleware | Subscriber middleware: skip if key seen (TTL) | Completed |
| **M2** Docs | Key generation patterns | Completed |

---

## Version v2.5 - Observability

**Shipped (package 1.4.0):** **`NatsMetricsContract`** + **`NullNatsMetrics`** / **`InMemoryNatsMetrics`**; optional publish counters and latency histogram from **`NatsPublisher`** when **`nats_basis.observability.metrics_enabled`**; **`CorrelationLogContext`** for HTTP logs; **`EnvelopeDataRedactor`** and **`RedactedEnvelopeLogInboundMiddleware`**; Artisan **`nats:ping`** and **`NatsV2::ping()`**; docs [docs/v2/OBSERVABILITY.md](v2/OBSERVABILITY.md).

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Metrics | Counters/histograms interface; optional Prometheus/Otel bridge | Completed |
| **M2** Logging | Correlation ID; redaction hooks for `data` | Completed |
| **M3** Health | `nats:ping` / readiness; optional route recipe | Completed |

---

## Version v2.6 - Security & config hardening

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Auth | JWT/NKey/token rotation docs; validate config on boot | Planned |
| **M1** Auth | Optional encrypted env for secrets | Planned |
| **M2** TLS | TLS-first production profile | Planned |
| **M3** ACLs | Optional publish/subscribe allowlist | Planned |

---

## Version v2.7 - Advanced features

| Area | Deliverable | Status |
|------|-------------|--------|
| **M1** Headers | Header helpers; lightweight W3C trace context | Planned |
| **M2** Clustering | Multi-connection docs; connection selection helper | Planned |
| **M3** Patterns | Optional outbox recipe / thin class | Planned |

---

## Summary (indicative)

| Version | Focus | Status |
|---------|--------|--------|
| v2.0 | Foundation, wrapper on basis client, publisher, envelope, `NatsV2`, migration docs | Completed |
| v2.1 | Subscribers, `InboundMessage`, `nats:v2:listen`, middleware, events | Completed |
| v2.2 | JetStream on basis client | Completed |
| v2.3 | Queue driver + DLQ + retry/backoff | Completed |
| v2.4 | Idempotency | Completed |
| v2.5 | Metrics, logging, health | Completed |
| v2.6 | Security, TLS, ACLs | Planned |
| v2.7 | Advanced headers, clustering, optional outbox | Planned |

---

*Document version: 1.8 - v2.2–v2.5 shipped as package 1.4.0.*

**User docs:** [docs/v2/README.md](v2/README.md) · [GUIDE](v2/GUIDE.md) · [SUBSCRIBER](v2/SUBSCRIBER.md) · [JETSTREAM](v2/JETSTREAM.md) · [QUEUE](v2/QUEUE.md) · [IDEMPOTENCY](v2/IDEMPOTENCY.md) · [OBSERVABILITY](v2/OBSERVABILITY.md) · [CORRELATION](v2/CORRELATION.md) · [MIGRATION](v2/MIGRATION.md)
