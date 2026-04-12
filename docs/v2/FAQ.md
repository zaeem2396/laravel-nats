# v2 FAQ

**Is this a separate NATS client?** No. v2 is a **Laravel wrapper** on [basis-company/nats](https://packagist.org/packages/basis-company/nats): this package adds config, facades, and the envelope; the dependency owns the protocol.

**NatsV2 subscribe (1.3.0+)?** Use `NatsV2::subscribe` with `InboundMessage` and a `process()` loop, or `php artisan nats:v2:listen`. See [SUBSCRIBER](SUBSCRIBER.md). Legacy `Nats::subscribe` remains available.

**Correlation / Request-ID on messages?** See [CORRELATION.md](CORRELATION.md) (`X-Request-Id`, `Nats-Correlation-Id`, optional publish injection).

**Why @deprecated on `Nats`?** Soft deprecation only: new **publish** code should use `NatsV2`. For **new** subscribe code on the basis stack, prefer `NatsV2::subscribe`. **Queue:** use **`nats_basis`** (1.4.0+, [QUEUE.md](QUEUE.md)) on the basis client, or keep **`nats`** on legacy. **JetStream:** basis helpers from 1.4.0+; legacy `Nats::jetstream()` unchanged. Details in [MIGRATION](MIGRATION.md).

**`nats` vs `nats_basis` queue driver?** **`nats`** uses `LaravelNats\Core\Client`. **`nats_basis`** uses `ConnectionManager` / `Basis\Nats\Client` with the same job JSON for `queue:work`. See [QUEUE.md](QUEUE.md).

**Mix facades?** Yes, per subject or service boundary.

**Is the envelope required?** Only if you use `NatsV2::publish`.

**JetStream on NatsV2?** Yes from **1.4.0+**: `NatsV2::jetstream()`, publish/pull helpers, and `nats:v2:jetstream:*` commands ([JETSTREAM.md](JETSTREAM.md)). Legacy `Nats::jetstream()` remains available.

**Idempotent subscribers?** From **1.4.0+**, add **`idempotency_key`** to the publish payload (or header), enable **`NATS_IDEMPOTENCY_ENABLED`**, register **`IdempotencyInboundMiddleware`**, and use a shared cache store ([IDEMPOTENCY.md](IDEMPOTENCY.md)).

**Metrics, redacted logs, NATS health?** From **1.4.0+**, see [OBSERVABILITY.md](OBSERVABILITY.md) (`NatsMetricsContract`, `nats:ping`, envelope redaction).

**Validate config or enforce subject allowlists?** From **1.5.0+**, see [SECURITY.md](SECURITY.md): optional boot validation (`NATS_BASIS_VALIDATE_CONFIG`), TLS expectations in production (`NATS_TLS_REQUIRE_IN_PRODUCTION`), optional ACL for **`NatsV2`** publish / subscribe / JetStream publish paths, and **`nats:v2:config:validate`**. Subject ACL does **not** wrap the **`nats_basis`** queue driver’s internal publishes.

**Overhead?** One JSON encode and UUID per publish; negligible vs network.

## See also

- [Roadmap](../ROADMAP_V2_NATSPHP.md)
- [SECURITY.md](SECURITY.md)

