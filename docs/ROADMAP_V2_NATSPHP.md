# Roadmap: Laravel NATS (basis-company/nats foundation)

This roadmap refactors **laravel-nats** to sit on **[basis-company/nats](https://github.com/basis-company/nats.php)** (Packagist: `basis-company/nats`). No manual NATS wire protocol in application code.

**Principles:** Laravel-first (container, config, facades), small SOLID classes, production-oriented, avoid overengineering.

---

## Version v2.0 — Foundation

**Goals:** Core connectivity on `basis-company/nats`; config-driven multi-connection manager; standardized publish envelope; facade entry point. **Out of scope:** queue driver, JetStream, full subscriber runtime (stubs/wiring only as needed).

**Module 1: Migration strategy**
- Submodule: **Backward compatibility** — dual stack: legacy `Nats` / `NatsManager` / existing queue & JetStream code remain usable alongside `NatsV2` until documented cutover points.
- Submodule: **Deprecation plan** — phased `@deprecated` on legacy entry points; timeline tied to v2.2+ feature parity (JetStream + queue on basis client); no silent removals within v2.x minors.
- Submodule: **Upgrade guide** — published doc: config mapping (`config/nats.php` → `nats_basis` + future unified config), facade usage, testing checklist per minor.

**Module 2: Dependency & boundaries**
- Submodule: Composer require `basis-company/nats`; document PHP/Laravel matrix.
- Submodule: Namespace layout `Connection/`, `Publisher/`, `Subscriber/` (stubs only where needed), `Support/`.
- Submodule: Note in upgrade guide when `LaravelNats\Core\Client` becomes legacy-only.

**Module 3: Configuration**
- Submodule: `config/nats_basis.php` — `default`, `connections[]` (host, port, timeout, auth, TLS flags).
- Submodule: Env mapping (`NATS_*` / `NATS_BASIS_*`) and `vendor:publish` tag.
- Submodule: Envelope schema version key (`envelope_version` default `v1`).

**Module 4: Connection integration (nats.php)**
- Submodule: `ConnectionManager` — resolve `Basis\Nats\Client` per named connection; lazy connect; disconnect/disconnectAll.
- Submodule: Map Laravel config → `Basis\Nats\Configuration` (user/pass, token, JWT, NKey, TLS paths).
- Submodule: Optional PSR-3 logger injection from Laravel `Log` (configurable in v2.1+).

**Module 5: Publisher API**
- Submodule: `MessageEnvelope` — `{ id, type, version, data }` (UUID v4 for `id`, `type` = subject).
- Submodule: `NatsPublisher` — JSON body + optional NATS headers (HPUB via `Basis\Nats\Message\Payload`).
- Submodule: `NatsV2Gateway` + `NatsV2` facade — `publish($subject, $payload, $headers = [])`, `connection(?string $name)`.

**Module 6: Service provider & container**
- Submodule: Register `ConnectionManager`, `NatsPublisher`, `nats.v2` gateway; merge config.
- Submodule: Keep existing `NatsServiceProvider` for legacy features during migration (see Migration strategy).

**Module 7: Quality**
- Submodule: Unit tests for envelope + config factory; PHPStan for new code.
- Submodule: README / docs pointer for v2.0 basis stack.

---

## Version v2.1 — Subscriber system

**Goals:** First-class subscriptions and inbound message handling; Laravel-friendly hooks without a heavy framework inside the package.

**Module 1: Subscriber API**
- Submodule: `SubscriberContract` + implementation wrapping `Basis\Nats\Client::subscribe` / `subscribeQueue`.
- Submodule: Subject validation, queue group support, graceful unsubscribe.

**Module 2: Runtime loop**
- Submodule: Long-running `process()` documented for workers; optional `php artisan nats:listen`-style command.
- Submodule: Signal handling notes (pcntl) for Supervisor/systemd.

**Module 3: Message inbound model**
- Submodule: DTO for consumed messages (subject, payload, headers, reply-to) decoupled from basis `Msg`.
- Submodule: Optional deserialization of v2 envelope on consume.

**Module 4: Laravel developer experience (DX)**
- Submodule: **Event integration** — opt-in: dispatch Laravel `Event` classes (or generic wrapper event) when a message arrives; config-driven subject → event mapping kept simple (closure or class name list, not a DSL).
- Submodule: **Middleware hooks** — small stack around inbound handling (e.g. logging, envelope decode, exception wrapping); same idea as HTTP middleware, minimal interface.

**Module 5: Sensible defaults**
- Submodule: **Subject conventions** — documented defaults (`domain.action`, `domain.entity.verb`); optional config validation warn-only mode for typos.

**Module 6: Observability (baseline)**
- Submodule: Structured log channel on connect/subscribe/error.
- Submodule: Optional request-ID / correlation header convention aligned with publisher.

**Module 7: Migration strategy (incremental)**
- Submodule: Doc update: how v1 subject consumers map to v2.1 subscriber API; no forced migration of Artisan commands yet.

---

## Version v2.2 — JetStream support

**Goals:** Streams, consumers, publish/consume using `basis-company/nats` (`Client::api()`, stream/consumer APIs) — **before** the Laravel queue driver so delayed/durable job patterns can build on one JetStream baseline.

**Module 1: JetStream facade layer**
- Submodule: Thin `JetStreamManager` (or equivalent) delegating to `Basis\Nats\Api` and packaged stream/consumer helpers.
- Submodule: Config for stream names, consumer durability, ack policies; reuse `ConnectionManager` connections.

**Module 2: Publish path**
- Submodule: JetStream-aware publish (subject + headers) with optional v2 envelope.

**Module 3: Consume path**
- Submodule: Pull consumer helper; batch fetch aligned with basis client.
- Submodule: Artisan commands ported from v1 only where they still add value (thin wrappers).

**Module 4: Sensible defaults**
- Submodule: **Default stream config** — documented starter template (retention, storage, max age/size) suitable for apps, not exhaustive tuning.
- Submodule: Sensible **consumer** defaults (explicit ack, modest ack wait) as config presets.

**Module 5: Migration strategy (incremental)**
- Submodule: Upgrade guide section: v1 JetStream client usage vs v2.2 manager; feature parity checklist.

---

## Version v2.3 — Queue driver + DLQ

**Goals:** Laravel `nats` queue driver on the basis client **after** JetStream land (v2.2) so the driver can lean on JetStream where needed (e.g. delayed/retry) without redesign. **Retry, DLQ, and failed-job handling ship in this version** — not a separate later release.

**Module 1: Connector & queue implementation**
- Submodule: Queue connector implementing Laravel’s contract using `ConnectionManager` / `Basis\Nats\Client`.
- Submodule: `NatsQueue` push/pop with job payload compatible with v2 envelope where practical.

**Module 2: Worker integration**
- Submodule: `queue:work` compatibility; retries/backoff wired to Laravel options.
- Submodule: Supervisor/systemd documentation.

**Module 3: DLQ & failed jobs**
- Submodule: Failed job provider using Laravel defaults (DB/files).
- Submodule: **DLQ convention** — single documented approach (e.g. JetStream stream or subject naming for poison messages); optional replay notes in docs — no separate “DLQ product” unless trivial Artisan listing.

**Module 4: Retry & backpressure (practical)**
- Submodule: Central **retry/backoff** defaults in config (max attempts, backoff strategy) shared with queue worker; align with Laravel `retryUntil` / job middleware where it’s a few lines of glue.
- Submodule: **Backpressure** — light-touch only (e.g. max in-flight per worker via config + simple counter), not a full scheduler.

**Module 5: Laravel developer experience (DX)**
- Submodule: **Job integration** — clear contract for what gets serialized (job + envelope metadata); helpers to push from domain code without duplicating subject strings everywhere.
- Submodule: **Middleware hooks** — Laravel job middleware compatibility documented; optional NATS-specific middleware (e.g. flush logs, span context) if low cost.

**Module 6: Sensible defaults**
- Submodule: **Default retry/backoff config** — shipped config stub matching Laravel expectations and JetStream delay story where enabled.
- Submodule: **Subject conventions** for queues (`queues.{name}`, etc.) documented and reflected in published config.

**Module 7: Migration strategy (incremental)**
- Submodule: Upgrade guide: migrating from v1 `NatsQueue` / connector to v2.3 driver; env and config diff.

---

## Version v2.4 — Idempotency

**Goals:** Safe replays without double side-effects.

**Module 1: Idempotency keys**
- Submodule: Envelope extension `idempotency_key` (optional); header mirror for non-JSON consumers.
- Submodule: Pluggable store interface (`Cache`, Redis, custom).

**Module 2: Consumer middleware**
- Submodule: Subscriber-side middleware: skip if key seen within TTL (builds on v2.1 middleware hook).
- Submodule: Documentation for key generation (aggregate id + command name).

---

## Version v2.5 — Observability

**Goals:** Production visibility without vendor lock-in.

**Module 1: Metrics**
- Submodule: Counters/histograms interface (null driver + optional Prometheus/OpenTelemetry bridge behind interface).
- Submodule: Tags: connection, subject, result (ack/nak/timeout).

**Module 2: Logging**
- Submodule: Correlation ID from envelope or headers; redaction hooks for sensitive `data` fields.

**Module 3: Health**
- Submodule: `nats:ping` / readiness using `Client::ping()`; optional route recipe in docs.

---

## Version v2.6 — Security & config hardening

**Goals:** Safer defaults for multi-tenant and regulated environments.

**Module 1: Authentication**
- Submodule: Document JWT/NKey/token rotation; validate config on boot (e.g. missing TLS file paths).
- Submodule: Optional Laravel encrypted env for secrets (documentation + small helper if needed).

**Module 2: TLS**
- Submodule: Document TLS-first production profile; expose options the basis client already supports.

**Module 3: Subject ACLs (app-level)**
- Submodule: Optional allowlist/denylist for publish/subscribe to catch misconfiguration early.

---

## Version v2.7 — Advanced features

**Goals:** Headers, clustering, optional patterns — only where clearly useful.

**Module 1: Headers & metadata**
- Submodule: Header helpers; W3C trace context helpers (lightweight).

**Module 2: Clustering**
- Submodule: Document multiple connections (core vs JetStream leaf); simple connection selection helper if it avoids copy-paste.

**Module 3: Optional patterns**
- Submodule: Outbox-style publisher (DB transaction + relay) as documented recipe or thin optional class — not a second framework.

---

## Summary timeline (indicative)

| Version | Focus |
|---------|--------|
| v2.0 | Foundation, basis client, publisher, envelope, `NatsV2`, **migration strategy** (dual stack, deprecation notes, upgrade guide seed) |
| v2.1 | Subscribers, runtime loop, inbound DTOs, **DX** (events, subscriber middleware), **sensible defaults** (subject conventions), baseline logging |
| v2.2 | **JetStream** (manager, pub/sub, artisan thin wrappers), **sensible defaults** (stream/consumer presets) |
| v2.3 | **Queue driver + DLQ + retry/backoff** in one release, worker docs, **DX** (job integration, job middleware), **sensible defaults** (queue subjects, retry config) |
| v2.4 | Idempotency |
| v2.5 | Metrics, logging, health |
| v2.6 | Security & TLS & optional subject ACLs |
| v2.7 | Advanced headers, clustering docs, optional outbox recipe |

---

*Document version: 1.1 — JetStream before queue, DLQ with queue, DX / sensible defaults / migration strategy modules added.*

**v2 docs:** [index](v2/README.md)

*v2.0 publisher stack: implemented; subscriber/JetStream-on-basis: see roadmap sections.*

**Next milestone:** v2.1 subscriber system (see roadmap body).

*Module 1 (migration strategy): dual-stack policy documented in [docs/v2/MIGRATION.md](v2/MIGRATION.md); `Nats`, `NatsManager`, and `Core\Client` carry `@deprecated` for new integrations as of 2.0.0.*
