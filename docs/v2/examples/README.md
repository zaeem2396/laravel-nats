# v2 examples

Focused snippets for the **NatsV2** stack. Prefer these alongside the full guides linked below. Security, validation, and ACL: [SECURITY.md](../SECURITY.md).

## Subscriber basics

- [Basic subscribe](01-basic-subscribe.md)
- [Queue group](02-queue-group.md)
- [Middleware](03-middleware.md)
- [Events](04-events.md)
- [Artisan listen](05-artisan-listen.md)
- [Unsubscribe](06-unsubscribe.md)
- [Named connection](07-named-connection.md)
- [Envelope payload](08-envelope-payload.md)
- [Wildcards](09-wildcards.md)

## JetStream & queue

- [JetStream info (CLI)](10-jetstream-info.md)
- [Laravel queue (`nats_basis`)](12-queue-nats-basis.md) — [QUEUE.md](../QUEUE.md)

## Production patterns

- [Reconnect after dropped session](11-reconnect.md) (1.6.2+) — [GUIDE.md](../GUIDE.md)
- [Config validation & subject ACL](13-security-acl.md) (1.5.0+) — [SECURITY.md](../SECURITY.md)
- [Idempotent publish/subscribe](17-idempotency.md) (1.4.0+) — [IDEMPOTENCY.md](../IDEMPOTENCY.md)
- [Request / reply & drain](16-request-reply.md) — [CLIENT_FEATURES.md](../CLIENT_FEATURES.md)

## Advanced (1.6.0+)

- [W3C trace context](15-trace-context.md) — [TRACE_CONTEXT.md](../TRACE_CONTEXT.md)
- [Transactional outbox drain](14-outbox.md) — [OUTBOX.md](../OUTBOX.md)
- Connection selection: [CONNECTION_SELECTION.md](../CONNECTION_SELECTION.md) (also illustrated in [named connection](07-named-connection.md))
