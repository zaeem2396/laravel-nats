# v2 documentation index

**Release mapping:** v2.6 (security and config hardening) ships in package **1.5.0**; v2.7 advanced features ship in package **1.6.0**; **1.6.1** improves test coverage and CI. See [ROADMAP.md](../ROADMAP.md) and [CHANGELOG.md](../../CHANGELOG.md).


The **NatsV2** stack (package **1.3.0+** for pub/sub; **1.4.0+** for basis JetStream, the **`nats_basis`** queue driver, optional idempotency, and observability including **`nats:ping`**; **1.5.0+** for security validation and optional subject ACL; **1.5.1** documentation refresh; **1.5.2** README/docs index and Docker setup refresh; **1.6.0+** for v2.7 trace context, connection selection, and outbox recipe; **1.6.1** test coverage and CI) is a **Laravel wrapper** around **[basis-company/nats](https://packagist.org/packages/basis-company/nats)** - use these pages for `NatsV2`, `config/nats_basis.php`, and the JSON envelope.

- [Guide](GUIDE.md)
- [Subscriber](SUBSCRIBER.md)
- [JetStream (NatsV2)](JETSTREAM.md)
- [Queue (`nats_basis`)](QUEUE.md)
- [Idempotency](IDEMPOTENCY.md)
- [Observability](OBSERVABILITY.md)
- [Security & ACL](SECURITY.md) (1.5.0+)
- [Client protocol features](CLIENT_FEATURES.md) (cluster seeds, request/no responders, headers, drain helper)
- [Trace context](TRACE_CONTEXT.md) (W3C `traceparent` / `tracestate`) (1.6.0+)
- [Connection selection](CONNECTION_SELECTION.md) (subject-prefix routing to named connections) (1.6.0+)
- [Outbox recipe](OUTBOX.md) (1.6.0+)
- [Correlation headers](CORRELATION.md)
- [Migration & upgrade strategy](MIGRATION.md)
- [FAQ](FAQ.md)
